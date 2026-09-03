<?php

use App\Models\Events\Show;
use App\Models\Events\Ticket;
use App\Support\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * tickets.currency held a display symbol; it now holds an ISO 4217 code and
 * every price surface formats through ICU (see App\Support\Currency).
 *
 * The map below is the old hand-maintained catalog, plus "AU": four
 * published Australian events stored that literal string (393 rows, the
 * oldest from 2020) because the catalog had nothing for them, and rendered
 * "AU25.00" on the live site ever since.
 *
 * events.price_range is a string precomputed from the tiers, so it is
 * rebuilt for every event that has tickets. Through the query builder, not
 * Event::save(): Event is Scout-searchable and would reindex on every save,
 * and the string is not in the index anyway.
 */
return new class extends Migration
{
    private const SYMBOL_TO_CODE = [
        '$' => 'USD',
        '€' => 'EUR',
        '£' => 'GBP',
        '¥' => 'JPY',
        'C$' => 'CAD',
        'MX$' => 'MXN',
        'CN¥' => 'CNY',
        '₩' => 'KRW',
        'A$' => 'AUD',
        'HK$' => 'HKD',
        'NT$' => 'TWD',
        '฿' => 'THB',
        'AU' => 'AUD',
    ];

    public function up(): void
    {
        foreach (self::SYMBOL_TO_CODE as $symbol => $code) {
            DB::table('tickets')->where('currency', $symbol)->update(['currency' => $code]);
        }

        // Anything left that is not a known code is left alone and reported,
        // never silently rewritten: the next save of that event will ask for
        // a currency, and the live page prints the stored value verbatim.
        $unknown = DB::table('tickets')
            ->whereNotIn('currency', Currency::codes())
            ->selectRaw('currency, COUNT(*) as rows_count')
            ->groupBy('currency')
            ->get();

        foreach ($unknown as $row) {
            Log::warning("Ticket currency '{$row->currency}' is not an ISO code ({$row->rows_count} rows left as-is).");
        }

        $this->recomputePriceRanges();
    }

    public function down(): void
    {
        foreach (self::SYMBOL_TO_CODE as $symbol => $code) {
            if ($symbol === 'AU') {
                continue; // AUD reverts to the catalog symbol, not the typo.
            }

            DB::table('tickets')->where('currency', $code)->update(['currency' => $symbol]);
        }

        $this->recomputePriceRanges();
    }

    private function recomputePriceRanges(): void
    {
        $showsHaveSoftDeletes = Schema::hasColumn('shows', 'deleted_at');

        DB::table('events')->select('id', 'updated_at', 'price_range')->orderBy('id')->chunkById(500, function ($events) use ($showsHaveSoftDeletes) {
            foreach ($events as $event) {
                $shows = DB::table('shows')->where('event_id', $event->id)->orderBy('date');
                if ($showsHaveSoftDeletes) {
                    $shows->whereNull('deleted_at');
                }
                $firstShowId = $shows->value('id');
                if (! $firstShowId) {
                    continue;
                }

                $tickets = DB::table('tickets')
                    ->where('ticket_type', Show::class)
                    ->where('ticket_id', $firstShowId)
                    ->get(['name', 'ticket_price', 'currency']);
                if ($tickets->isEmpty()) {
                    continue;
                }

                $range = Ticket::getPriceRange(
                    $tickets->pluck('ticket_price')->map(fn ($p) => (float) $p)->all(),
                    $tickets->first()->currency,
                    $tickets->pluck('name')->all(),
                );

                if ($range === $event->price_range) {
                    continue;
                }

                // Guarded on updated_at: this runs with the site live, and an
                // organizer saving the event meanwhile rewrites price_range
                // from its own tiers (Ticket::handleTickets) and bumps
                // updated_at. If that landed after this row was read, the
                // string computed here is the stale one and must not win.
                DB::table('events')
                    ->where('id', $event->id)
                    ->when($event->updated_at === null, fn ($q) => $q->whereNull('updated_at'), fn ($q) => $q->where('updated_at', $event->updated_at))
                    ->update(['price_range' => $range]);
            }
        });
    }
};
