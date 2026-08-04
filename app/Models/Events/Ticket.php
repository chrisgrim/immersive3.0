<?php

namespace App\Models\Events;

use App\Models\Event;
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

        foreach ($tiers as $name => $ticketData) {
            $prices[] = $ticketData['ticket_price'];
            $names[] = $name;
            $currency = $ticketData['currency'];
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
                            'description' => $ticketData['description'],
                            'currency' => $ticketData['currency'],
                            'ticket_price' => $ticketData['ticket_price'],
                            'updated_at' => $now,
                        ]);
                    }

                    // Shows that don't have this tier yet get it in one bulk insert.
                    foreach ($showIds->diff($existing->pluck('ticket_id')) as $showId) {
                        $rowsToInsert[] = [
                            'ticket_type' => Show::class,
                            'ticket_id' => $showId,
                            'name' => $name,
                            'description' => $ticketData['description'],
                            'currency' => $ticketData['currency'],
                            'ticket_price' => $ticketData['ticket_price'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // Chunked so an enormous schedule can never exceed the DB's
                // bind-parameter limit.
                collect($rowsToInsert)
                    ->chunk(self::INSERT_CHUNK)
                    ->each(fn ($chunk) => self::insert($chunk->values()->all()));
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
            $formattedLowestPrice = number_format($lowestPrice, 2);
            $formattedLowestPrice = preg_replace('/\.00$/', '', $formattedLowestPrice);
            $first = $currency.$formattedLowestPrice;
        }

        if (count($prices) > 1) {
            $formattedHighestPrice = number_format($prices[0], 2);
            $formattedHighestPrice = preg_replace('/\.00$/', '', $formattedHighestPrice);

            // If lowest price is PWYC but there are higher prices, show the range
            if ($hasPWYC) {
                return $pricerange = 'PWYC - '.$currency.$formattedHighestPrice;
            } else {
                return $pricerange = $first.' - '.$currency.$formattedHighestPrice;
            }
        } else {
            return $pricerange = $first;
        }
    }
}
