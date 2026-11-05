<?php

namespace App\Actions\Projects;

use App\Enums\CreditDepartment;
use App\Enums\CreditKind;
use App\Models\AuditLog;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a whole credits list (MCP set-credits). A run's rows go through
 * CreditPolicy department by department, removed ones included, so a curator
 * cannot empty a crew list. A show's rows are its writers.
 */
class SetCredits
{
    public const RULES = [
        'credits' => ['present', 'array', 'max:500'],
        'credits.*.id' => ['nullable', 'integer'],
        'credits.*.artist_id' => ['required', 'integer'],
        'credits.*.department' => ['required', 'string'],
        'credits.*.role' => ['nullable', 'string', 'max:255'],
        'credits.*.character' => ['nullable', 'string', 'max:255'],
        'credits.*.kind' => ['nullable', 'string'],
        'credits.*.covers' => ['nullable', 'string', 'max:2000'],
        'credits.*.credited_as' => ['nullable', 'string', 'max:255'],
        'credits.*.starts_on' => ['nullable', 'date'],
        'credits.*.ends_on' => ['nullable', 'date'],
        'credits.*.note' => ['nullable', 'string', 'max:2000'],
    ];

    /** @return array<int, array<string, mixed>> */
    public function handle(Project|Event $target, array $rows, User $actor): array
    {
        $this->assertRows($rows);

        return DB::transaction(function () use ($target, $rows, $actor) {
            $held = $target->credits()->approved()->lockForUpdate()->get()->keyBy('id');
            $sent = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);

            if ($sent->diff($held->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['credits' => 'A credit id is not on this list.']);
            }

            $departments = $held->pluck('department')->concat(collect($rows)->pluck('department'))->unique();

            foreach ($departments as $department) {
                $this->authorize($target, (string) $department, $actor);
            }

            $held->except($sent->all())->each->delete();

            foreach (array_values($rows) as $rank => $row) {
                $fields = [...Arr::except($row, ['id']), 'kind' => $row['kind'] ?? CreditKind::Regular->value, 'rank' => $rank];

                isset($row['id']) ? $held[(int) $row['id']]->update($fields) : $target->credits()->create($fields);
            }

            AuditLog::record($target, 'credits.set', $actor, ['rows' => count($rows)]);

            return $target->credits()->approved()->orderBy('rank')->get()->toArray();
        });
    }

    private function assertRows(array $rows): void
    {
        foreach ($rows as $row) {
            if (! in_array($row['department'], CreditDepartment::values(), true)) {
                throw ValidationException::withMessages(['credits' => "Unknown department: {$row['department']}."]);
            }

            if (isset($row['kind']) && ! in_array($row['kind'], CreditKind::values(), true)) {
                throw ValidationException::withMessages(['credits' => "Unknown kind: {$row['kind']}."]);
            }
        }

        $artists = collect($rows)->pluck('artist_id')->unique();
        $live = \App\Models\Artist::whereKey($artists)->whereNull('merged_into_id')->count();

        if ($live !== $artists->count()) {
            throw ValidationException::withMessages(['credits' => 'An artist id does not exist, or has been merged.']);
        }
    }

    private function authorize(Project|Event $target, string $department, User $actor): void
    {
        $gate = Gate::forUser($actor);

        $target instanceof Project
            ? $gate->authorize('editOnProject', [Credit::class, $target])
            : $gate->authorize('editOnRun', [Credit::class, $target, $department]);

        if ($target instanceof Project && $department !== CreditDepartment::Writing->value) {
            throw ValidationException::withMessages(['credits' => 'A show carries only its writers; other credits belong to a run.']);
        }
    }
}
