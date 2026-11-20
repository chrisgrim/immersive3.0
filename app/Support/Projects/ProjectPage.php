<?php

namespace App\Support\Projects;

use App\Models\Event;
use App\Models\Events\Ticket;
use App\Models\Project;
use App\Scopes\LatestPublishedFirstScope;
use App\Support\Currency;
use Carbon\Carbon;
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

        InstanceAddress::primeFirstDays($runs);

        $rows = $runs->map(fn (Event $run) => RunRow::for($run, $project->name));

        $now = self::withOffers($rows->where('bucket', RunRow::NOW)->sortBy('start')->values(), $runs);
        $coming = self::withOffers($rows->where('bucket', RunRow::COMING)->sortBy('start')->values(), $runs);
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

    /**
     * Each current run's cheapest ticket and when it went on sale, for the
     * tickets block Google reads. One query for all of them.
     */
    private static function withOffers(Collection $rows, Collection $runs): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $cheapest = Ticket::query()
            ->where('ticket_type', (new Event)->getMorphClass())
            ->whereIn('ticket_id', $rows->pluck('id'))
            ->whereNotNull('ticket_price')
            ->get(['ticket_id', 'ticket_price', 'currency'])
            ->groupBy('ticket_id')
            ->map(fn ($tickets) => $tickets->sortBy('ticket_price')->first());

        return $rows->map(function (array $row) use ($cheapest, $runs) {
            $ticket = $cheapest->get($row['id']);
            $published = $runs->firstWhere('id', $row['id'])?->published_at;

            return $row + [
                'price' => $ticket ? (float) $ticket->ticket_price : null,
                'currency' => $ticket ? (Currency::normalize($ticket->currency) ?: 'USD') : null,
                'on_sale_from' => $published ? Carbon::parse($published)->toIso8601String() : null,
            ];
        });
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
    public static function fromPrices(Collection $runs): array
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
