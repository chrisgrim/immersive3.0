<?php

namespace App\Http\Controllers\Creation;

use App\Actions\Projects\CopyCredits;
use App\Enums\CreditDepartment;
use App\Enums\CreditKind;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Credit;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A run's credits panel, outside can:manage,event because curators do not
 * pass it. CreditPolicy decides every read and write, row by row.
 */
class EventCreditController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $this->authorize('viewOnRun', [Credit::class, $event]);

        $panel = $this->panel($request, $event);

        return $request->expectsJson()
            ? response()->json($panel)
            : view('projects.credits', ['pageCredits' => $panel]);
    }

    /** One row, added or edited (with `id`), and/or the run's Special Thanks. */
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'special_thanks' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'id' => ['nullable', 'integer'],
            'artist_id' => [Rule::requiredIf(! $request->has('special_thanks')), 'integer', Rule::exists('artists', 'id')->whereNull('merged_into_id')],
            'department' => [Rule::requiredIf(! $request->has('special_thanks')), 'string', Rule::in(CreditDepartment::values())],
            'role' => ['nullable', 'string', 'max:255'],
            'character' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', 'string', Rule::in(CreditKind::values())],
            'covers' => ['nullable', 'string', 'max:2000'],
            'credited_as' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:2000'],
            'rank' => ['nullable', 'integer'],
        ]);

        if (array_key_exists('special_thanks', $validated)) {
            $this->authorize('editThanks', [Credit::class, $event]);
            $event->forceFill(['special_thanks' => $validated['special_thanks']])->save();
            AuditLog::record($event, 'credits.thanks_updated', $request->user());
        }

        if (isset($validated['artist_id'])) {
            $this->saveRow($request, $event, $validated);
        }

        return response()->json($this->panel($request, $event));
    }

    private function saveRow(Request $request, Event $event, array $validated): void
    {
        $this->authorize('editOnRun', [Credit::class, $event, $validated['department']]);

        $fields = Arr::except($validated, ['id', 'special_thanks']);
        $fields['kind'] ??= CreditKind::Regular->value;
        $fields['rank'] ??= 0;

        if (empty($validated['id'])) {
            $credit = $event->credits()->create($fields);
        } else {
            $credit = $this->ownRow($event, (int) $validated['id']);
            // A curator cannot reach a production row by moving it to cast.
            $this->authorize('editOnRun', [Credit::class, $event, $credit->department]);
            $credit->update($fields);
        }

        AuditLog::record($credit, 'credit.saved', $request->user(), ['event_id' => $event->id]);
    }

    public function destroy(Request $request, Event $event, Credit $credit)
    {
        $credit = $this->ownRow($event, $credit->id);

        $this->authorize('editOnRun', [Credit::class, $event, $credit->department]);

        AuditLog::record($credit, 'credit.deleted', $request->user(), [
            'event_id' => $event->id,
            'artist_id' => $credit->artist_id,
            'department' => $credit->department,
            'role' => $credit->role,
        ]);

        $credit->delete();

        return response()->json($this->panel($request, $event));
    }

    public function copy(Request $request, Event $event, CopyCredits $copy)
    {
        $validated = $request->validate(['from_event_id' => ['required', 'integer', 'exists:events,id']]);

        $added = $copy->handle(Event::findOrFail($validated['from_event_id']), $event, $request->user());

        return response()->json(['added' => $added] + $this->panel($request, $event));
    }

    private function ownRow(Event $event, int $id): Credit
    {
        $credit = $event->credits()->approved()->find($id);

        return $credit ?? throw ValidationException::withMessages(['id' => 'That credit is not on this run.']);
    }

    /** @return array<string, mixed> */
    private function panel(Request $request, Event $event): array
    {
        $user = $request->user();
        $present = fn (Credit $credit, string $source) => [
            'id' => $credit->id,
            'source' => $source,
            'artist_id' => $credit->artist_id,
            'artist' => $credit->artist?->name,
            ...$credit->only(['department', 'role', 'character', 'kind', 'covers', 'credited_as', 'note', 'rank']),
            'starts_on' => $credit->starts_on?->toDateString(),
            'ends_on' => $credit->ends_on?->toDateString(),
        ];

        $canThanks = $user->can('editThanks', [Credit::class, $event]);

        return [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'linked' => $event->isLinked(),
            ],
            'credits' => $event->credits()->approved()->with('artist:id,name')->orderBy('department')->orderBy('rank')->orderBy('id')->get()
                ->map(fn (Credit $credit) => $present($credit, 'run'))->values(),
            // A run's credits are its own rows plus its show's writers.
            'show_writers' => $event->project
                ? $event->project->writers()->approved()->with('artist:id,name')->orderBy('rank')->orderBy('id')->get()
                    ->map(fn (Credit $credit) => $present($credit, 'show'))->values()
                : [],
            'editable_departments' => array_values(array_filter(
                CreditDepartment::values(),
                fn (string $department) => $user->can('editOnRun', [Credit::class, $event, $department]),
            )),
            'kinds' => CreditKind::values(),
            'special_thanks' => $canThanks ? $event->special_thanks : null,
            'can_edit_thanks' => $canThanks,
            'can_copy' => $user->can('copyOnto', [Credit::class, $event]),
            'other_runs' => $event->project
                ? $event->project->runs()->whereKeyNot($event->id)->get(['id', 'name', 'project_id', 'instance_slug', 'slug', 'status', 'organizer_id', 'held_for_staff'])
                    // An unannounced run is its own team's to see.
                    ->filter(fn (Event $run) => $run->status === Event::STATUS_PUBLISHED || $user->can('manage', $run))
                    ->map(fn (Event $run) => ['id' => $run->id, 'name' => $run->name, 'public_url' => $run->publicPath()])->values()
                : [],
        ];
    }
}
