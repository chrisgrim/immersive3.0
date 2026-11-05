<?php

namespace App\Support\Projects;

use App\Models\Event;
use App\Models\Events\Ticket;
use App\Models\Project;
use App\Scopes\LatestPublishedFirstScope;
use App\Support\Currency;
use Illuminate\Support\Collection;

/**
 * What the show's page says, from its published runs: nothing else appears.
 */
class ProjectPage
{
    /** @return array<string, mixed> */
    public static function for(Project $project): array
    {
        $runs = $project->runs()
            ->where('status', Event::STATUS_PUBLISHED)
            ->with(['organizer:id,name,slug,status', 'location', 'remotelocations', 'images'])
            ->get();

        $rows = $runs->map(fn (Event $run) => RunRow::for($run, $project->name));

        $now = $rows->where('bucket', RunRow::NOW)->sortBy('start')->values();
        $coming = $rows->where('bucket', RunRow::COMING)->sortBy('start')->values();
        $past = $rows->where('bucket', RunRow::PAST)->sortByDesc('start')->values();

        return [
            'run_count' => $rows->count(),
            'first' => $rows->sortBy('start')->first(),
            'latest' => $now->last() ?? $past->first() ?? $coming->first(),
            'now' => $now,
            'coming' => $coming,
            'past' => $past,
            'from' => self::fromPrices($runs),
            'image' => $project->largeImagePath ?? self::firstImage($runs, $rows),
        ];
    }

    /** Without a picture of its own, the earliest run's that has one. */
    private static function firstImage(Collection $runs, Collection $rows): ?string
    {
        return $rows->sortBy('start')
            ->map(fn (array $row) => $runs->firstWhere('id', $row['id'])?->largeImagePath)
            ->filter()
            ->first();
    }

    /**
     * The cheapest ticket across the runs on now or coming up, one per
     * currency, none without a current run.
     *
     * @return array<int, string>
     */
    private static function fromPrices(Collection $runs): array
    {
        $current = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->whereKey($runs->modelKeys())
            ->stillRunning()
            ->pluck('id');

        if ($current->isEmpty()) {
            return [];
        }

        return Ticket::query()
            ->where('ticket_type', (new Event)->getMorphClass())
            ->whereIn('ticket_id', $current)
            ->get(['ticket_price', 'currency'])
            ->groupBy(fn (Ticket $ticket) => Currency::normalize($ticket->currency) ?: 'USD')
            ->map(fn ($tickets, $code) => (float) $tickets->min('ticket_price') === 0.0
                ? 'Free'
                : 'From '.Currency::format($tickets->min('ticket_price'), $code))
            ->sort()
            ->values()
            ->unique()
            ->all();
    }
}
