<?php

namespace App\Models\Events;

use App\Models\Event;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ticket extends Model
{
    /** Rows per bulk insert — keeps any single statement well under the DB's bind-parameter limit. */
    private const INSERT_CHUNK = 500;

    /**
     * What protected variables are allowed to be passed to the database
     *
     * @var array
     */
    protected $fillable = ['name', 'ticket_price', 'ticket_id', 'ticket_type', 'description', 'type', 'currency'];

    /**
     * Ticket Belongs to the Show Model
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function ticket()
    {
        return $this->morphTo();
    }

    public static function handleTickets(\Illuminate\Http\Request $request, Event $event)
    {
        // Defence-in-depth: an omitted `tickets` field means "don't touch tickets",
        // but every path below treats an empty list as "the user removed every
        // tier" and deletes accordingly. Callers guard with isset(), so only a
        // malformed request reaches here with a non-array — bail rather than wipe.
        // An explicitly empty array still falls through, which is the real
        // "remove all tiers" instruction.
        if (! is_array($request->tickets)) {
            return;
        }

        // Tiers are uniform across an event's shows, so a name identifies a tier.
        // keyBy keeps the last of any duplicate name, matching the old
        // updateOrCreate loop where a repeated name simply overwrote itself.
        // Unlike the old loop, the collapsed list also drives the price ranges, so
        // a duplicated name contributes one price instead of one per submission —
        // the range now always describes tiers that actually exist.
        $tiers = collect($request->tickets)->keyBy('name');
        $submittedNames = $tiers->keys()->all();

        // Read the show ids fresh: saveShows runs before this and may have just
        // created rows an already-loaded $event->shows relation knows nothing of.
        $showIds = $event->shows()->pluck('id');

        $prices = [];
        $names = [];
        $currency = '';

        // Validation (EventUpdateRules) is the real guard: name, ticket_price and
        // currency are all required_with:tickets, and description is optional.
        // These fallbacks only keep a partial tier from fataling on an undefined
        // key if some future caller reaches here unvalidated — they mirror the
        // web wizard's own normalization ('' description) and the column default
        // for currency.
        foreach ($tiers as $name => $ticketData) {
            $prices[] = $ticketData['ticket_price'] ?? 0;
            $names[] = $name;
            $currency = $ticketData['currency'] ?? Currency::DEFAULT;
        }

        DB::transaction(function () use ($event, $tiers, $submittedNames, $showIds, $prices) {
            if ($showIds->isNotEmpty()) {
                // --- Drop removed tiers across every show in ONE delete. This
                //     whole block used to run a delete plus a select and a write
                //     per tier per show — 3 queries a show, which crawls now that
                //     recurrence expansion can produce hundreds of shows. ---
                self::where('ticket_type', Show::class)
                    ->whereIn('ticket_id', $showIds)
                    ->whereNotIn('name', $submittedNames)
                    ->delete();

                $existingByName = self::where('ticket_type', Show::class)
                    ->whereIn('ticket_id', $showIds)
                    ->get(['id', 'ticket_id', 'name'])
                    ->groupBy('name');

                $now = now();
                $rowsToInsert = [];

                foreach ($tiers as $name => $ticketData) {
                    $existing = $existingByName->get($name, collect());

                    // Every surviving row for this tier takes the same values,
                    // so one UPDATE covers all of them.
                    if ($existing->isNotEmpty()) {
                        self::whereIn('id', $existing->pluck('id'))->update([
                            'description' => $ticketData['description'] ?? '',
                            'currency' => $ticketData['currency'] ?? Currency::DEFAULT,
                            'ticket_price' => $ticketData['ticket_price'] ?? 0,
                            'updated_at' => $now,
                        ]);
                    }

                    // Shows that don't have this tier yet get it in one bulk insert.
                    foreach ($showIds->diff($existing->pluck('ticket_id')) as $showId) {
                        $rowsToInsert[] = [
                            'ticket_type' => Show::class,
                            'ticket_id' => $showId,
                            'name' => $name,
                            'description' => $ticketData['description'] ?? '',
                            'currency' => $ticketData['currency'] ?? Currency::DEFAULT,
                            'ticket_price' => $ticketData['ticket_price'] ?? 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // upsert(), not insert(). The block above is read-then-write:
                // it reads which shows are missing a tier, then writes them. Two
                // saves landing together both read "missing" and, with a plain
                // insert, both wrote — which is exactly how 148 duplicate rows
                // got into production, 147 of them created within the same
                // second. Matching on the same columns as the unique index
                // (see the tickets_owner_name_unique migration) makes the loser
                // of that race update the winner's row instead of adding a
                // second one, rather than erroring on the constraint.
                //
                // keyBy('name') above already collapses duplicates WITHIN one
                // request; this is the across-requests half, which that could
                // never cover.
                //
                // Chunked so an enormous schedule can never exceed the DB's
                // bind-parameter limit.
                collect($rowsToInsert)
                    ->chunk(self::INSERT_CHUNK)
                    ->each(fn ($chunk) => self::upsert(
                        $chunk->values()->all(),
                        ['ticket_type', 'ticket_id', 'name'],
                        ['description', 'currency', 'ticket_price', 'updated_at'],
                    ));
            }

            $event->priceranges()->delete();

            // Was a create() per tier; one insert instead.
            if ($prices) {
                $now = now();
                $event->priceranges()->insert(array_map(fn ($price) => [
                    'event_id' => $event->id,
                    'price' => $price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $prices));
            }
        });

        // Deliberately OUTSIDE the transaction: Event is Scout-Searchable and
        // scout.after_commit is false, so saving it indexes to Elasticsearch
        // immediately. Inside the transaction a later rollback would leave the
        // index describing rows that no longer exist.
        $event->update([
            'price_range' => self::getPriceRange($prices, $currency, $names),
        ]);

        $event->fresh()->searchable();
    }

    public static function getPriceRange($prices, $currency, $names = [])
    {
        rsort($prices);
        $lowestPrice = last($prices);

        // Check if any ticket name is "PWYC" (case insensitive)
        $hasPWYC = false;
        foreach ($names as $name) {
            if (strtoupper(trim($name)) === 'PWYC') {
                $hasPWYC = true;
                break;
            }
        }

        if ($hasPWYC) {
            $first = 'PWYC';
        } elseif ($lowestPrice == 0) {
            $first = 'Free';
        } else {
            $first = self::formatCompact($lowestPrice, $currency);
        }

        if (count($prices) > 1) {
            $highest = self::formatCompact($prices[0], $currency);

            // If lowest price is PWYC but there are higher prices, show the range
            return $hasPWYC ? 'PWYC - '.$highest : $first.' - '.$highest;
        }

        return $first;
    }

    /**
     * "$40", "$17.50", "A$25", "SGD 45", "₩144,000" — ICU formatting with a
     * whole amount's zero decimals dropped, which is what the price_range
     * strings on every card have always looked like.
     *
     * A stored value that is not an ISO code (only possible for rows the
     * 2026-09-02 migration could not map) keeps the old verbatim-prefix form
     * rather than being silently shown as dollars.
     */
    private static function formatCompact(float|int|string $amount, ?string $currency): string
    {
        if (Currency::isValid($currency)) {
            return Currency::format($amount, $currency, compact: true);
        }

        return $currency.preg_replace('/\.00$/', '', number_format((float) $amount, 2));
    }
}
