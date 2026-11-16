<?php

namespace App\Http\Controllers\Creation;

use App\Actions\Projects\LinkEventToProject;
use App\Actions\Projects\LinkReview;
use App\Actions\Projects\UnlinkEvent;
use App\Enums\CreditDepartment;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Services\NameChangeRequestService;
use App\Support\PhotoGallery;
use App\Support\Projects\InstanceAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * For the company that keeps a show's page (ProjectPolicy): the page itself,
 * the show's writers, the requests to join, and taking a run off. Who made it
 * and its address are a moderator's (AdminProjectController).
 */
class HostProjectController extends Controller
{
    /** One photo: the project page shows only its tall main picture. */
    public const MAX_PHOTOS = 1;

    public function __construct(private LinkEventToProject $links, private LinkReview $review) {}

    public function edit(Project $project)
    {
        return view('projects.edit', ['pageProject' => $this->pageData($project)]);
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/[\p{L}\p{N}]/u'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'writers' => ['sometimes', 'array'],
            'writers.*.id' => ['nullable', 'integer'],
            'writers.*.artist_id' => ['required', 'integer', Rule::exists('artists', 'id')->whereNull('merged_into_id')],
            'writers.*.role' => ['nullable', 'string', 'max:255'],
            'writers.*.credited_as' => ['nullable', 'string', 'max:255'],
        ]);

        // A new name from the show run's company waits for EI, as an event's
        // does; EI's own staff rename it straight away.
        $newName = isset($validated['name']) && trim($validated['name']) !== $project->name ? trim($validated['name']) : null;
        $staff = $request->user()->isModerator();
        $asks = $newName !== null && ! $staff;

        DB::transaction(function () use ($request, $project, $validated, $asks, $newName, $staff) {
            $project->update(Arr::only($validated, $asks ? ['description'] : ['name', 'description']));

            // The company typed the name it has back: nothing left to ask.
            // Staff really renaming it settle a waiting request (approved if
            // it is that name); staff saving other fields leave it waiting.
            if (isset($validated['name']) && ($staff ? $newName !== null : $newName === null)) {
                NameChangeRequestService::settleAfterDirectRename($project, $newName ?? $project->name);
            }

            if ($asks) {
                $pending = $project->nameChangeRequests()->where('status', 'pending')->first();
                $pending
                    ? $pending->update(['requested_name' => $newName])
                    : app(NameChangeRequestService::class)->handleNameChange($project, $newName);
            }

            if (array_key_exists('writers', $validated)) {
                $this->syncWriters($project, $validated['writers']);
            }

            AuditLog::record($project, 'project.page_updated', $request->user(), ['fields' => array_keys($validated)]);
        });

        return response()->json($this->pageData($project->refresh()));
    }

    /** Its one photo, tall (900 by 1200), saved the event way (PhotoGallery). */
    public function photos(Request $request, Project $project)
    {
        $request->validate(PhotoGallery::rules(self::MAX_PHOTOS));

        PhotoGallery::sync($project, $request, 'project-images', fn () => [900, 1200], self::MAX_PHOTOS);

        AuditLog::record($project, 'project.photos_updated', $request->user());

        return response()->json($this->pageData($project->refresh()));
    }

    /** The list as sent: kept rows updated, new ones added, the rest removed. */
    private function syncWriters(Project $project, array $writers): void
    {
        $held = $project->writers()->get()->keyBy('id');
        $sentIds = collect($writers)->pluck('id')->filter()->map(fn ($id) => (int) $id);

        if ($sentIds->diff($held->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['writers' => 'That writer is not on this show run.']);
        }

        $held->except($sentIds->all())->each->delete();

        // validated() rebuilds the list rule by rule, so its keys are the
        // order they were sent in, whatever order the array is in.
        ksort($writers);

        foreach (array_values($writers) as $rank => $writer) {
            $fields = [
                'artist_id' => $writer['artist_id'],
                'department' => CreditDepartment::Writing->value,
                'role' => $writer['role'] ?? null,
                'credited_as' => $writer['credited_as'] ?? null,
                'rank' => $rank,
            ];

            ! empty($writer['id'])
                ? $held[$writer['id']]->update($fields)
                : $project->credits()->create($fields);
        }
    }

    public function linkRequests(Project $project)
    {
        return response()->json($this->pendingRequests($project));
    }

    public function approveLink(Request $request, Project $project, ProjectLinkRequest $linkRequest)
    {
        $this->assertBelongs($project, $linkRequest);

        $this->links->approve($linkRequest, $request->user());

        return response()->json($this->pageData($project->refresh()));
    }

    public function rejectLink(Request $request, Project $project, ProjectLinkRequest $linkRequest)
    {
        $this->assertBelongs($project, $linkRequest);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $this->links->reject($linkRequest, $request->user(), $validated['note'] ?? null);

        return response()->json($this->pageData($project->refresh()));
    }

    public function unlink(Request $request, Project $project, Event $event, UnlinkEvent $unlink)
    {
        abort_unless($event->project_id === $project->id, 404);

        $unlink->handle($event, $request->user());

        return response()->json($this->pageData($project->refresh()));
    }

    private function assertBelongs(Project $project, ProjectLinkRequest $linkRequest): void
    {
        abort_unless($linkRequest->project_id === $project->id, 404);
    }

    private function pendingRequests(Project $project): array
    {
        // A draft is still its team's business: it waits on nobody yet.
        return $this->review->withRelations($project->linkRequests()->pending()->whereNotNull('submitted_at'))
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (ProjectLinkRequest $linkRequest) => $this->review->present($linkRequest))
            ->all();
    }

    /** @return array<string, mixed> */
    private function pageData(Project $project): array
    {
        $runs = $project->runs()->with('organizer:id,name')->get();
        InstanceAddress::primeFirstDays($runs);

        return [
            'id' => $project->id,
            'name' => $project->name,
            // A new name waiting for EI's approval.
            'pending_name' => $project->nameChangeRequests()->where('status', 'pending')->value('requested_name'),
            // EI's staff rename straight away, so their form starts from the real name.
            'renames_directly' => (bool) auth()->user()?->isModerator(),
            'slug' => $project->slug,
            'description' => $project->description,
            'largeImagePath' => $project->largeImagePath,
            'photos' => PhotoGallery::present($project),
            'max_photos' => self::MAX_PHOTOS,
            'public_url' => $project->publicPath(),
            'organizer' => $project->organizer?->only(['id', 'name']),
            'keeper' => $project->keeper()?->only(['id', 'name']),
            'runs' => $runs->map(fn (Event $run) => [
                'id' => $run->id,
                'name' => $run->name,
                'slug' => $run->slug,
                'status' => $run->status,
                'public_url' => $run->publicPath(),
                'organizer' => $run->organizer?->only(['id', 'name']),
            ])->values(),
            'writers' => $project->writers()->approved()->with('artist:id,name')->orderBy('rank')->orderBy('id')->get()
                ->map(fn (Credit $credit) => [
                    'id' => $credit->id,
                    'artist_id' => $credit->artist_id,
                    'artist' => $credit->artist?->name,
                    'role' => $credit->role,
                    'credited_as' => $credit->credited_as,
                ])->values(),
            'aggregate' => $this->aggregate($runs),
            'link_requests' => $this->pendingRequests($project),
        ];
    }

    /** Everyone across the show's runs, with run count and year span. Derived, never stored. */
    private function aggregate($runs): array
    {
        $years = $runs->mapWithKeys(fn (Event $run) => [$run->id => substr(InstanceAddress::startDay($run), 0, 4)]);

        return Credit::approved()
            ->where('creditable_type', (new Event)->getMorphClass())
            ->whereIn('creditable_id', $runs->modelKeys())
            ->with('artist:id,name')
            ->get()
            ->groupBy('artist_id')
            ->map(function ($credits) use ($years) {
                $spans = $credits->pluck('creditable_id')->unique()->map(fn ($id) => $years[$id]);

                return [
                    'artist_id' => $credits->first()->artist_id,
                    'artist' => $credits->first()->artist?->name,
                    'runs' => $spans->count(),
                    'first_year' => $spans->min(),
                    'last_year' => $spans->max(),
                ];
            })
            ->sortBy('artist')
            ->values()
            ->all();
    }
}
