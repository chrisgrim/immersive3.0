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
        // First fold names the database treats as the same (case, accents,
        // trailing spaces), keeping the last, then key by the name as sent.
        // Without the fold, "ga" and "GA" in one save reach the per-show and
        // event writes as two tiers, and the unique index settles them in a
        // different order on each side.
        $tiers = collect(self::foldCollationDuplicates($request->tickets))->keyBy('name');
        // As strings: keyBy turns an all-digit name like "10" into an integer
        // key, and an integer bound against the name column makes MySQL compare
        // numerically, which strict mode rejects mid-delete.
        $submittedNames = array_map('strval', $tiers->keys()->all());

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

        // Two saves of the same event landing together (an AI assistant firing
        // parallel update-event calls did this on prod, EI-LARAVEL-1H) deadlocked
        // on the tickets index. Locking the event row first, the same order the
        // schedule transaction in UpdateEventAction uses, makes concurrent saves
        // of one event queue up instead. The lock cannot help two DIFFERENT
        // events saving at once: new shows get the newest ids, so both writes
        // land in the same gap at the top of the tickets unique index and can
        // still deadlock. attempts: 3 retries that case. A retry is safe
        // because everything the closure depends on from the database is read
        // inside it. ei:backfill-event-tickets takes the same lock, so it can
        // never re-add a tier this save just removed.
        DB::transaction(function () use ($event, $tiers, $submittedNames, $prices) {
            Event::whereKey($event->id)->lockForUpdate()->first();

            // Read the show ids fresh, under the lock: saveShows runs before this
            // (or in a concurrent save) and may have just created or removed rows
            // an already-loaded $event->shows relation knows nothing of.
            $showIds = $event->shows()->pluck('id');

            self::syncEventTiers($event, $tiers, $submittedNames, $showIds);

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
                            'name' => (string) $name,
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
        }, attempts: 3);

        // Deliberately OUTSIDE the transaction so the index never describes
        // rows a rollback removed. syncSearchIndex() re-reads the event and is
        // batched to one reindex when UpdateEventAction is driving the save.
        // Keep this Eloquent update out of the retried closure too: after a
        // rolled-back first attempt Eloquent thinks price_range is already
        // saved, so the retry would silently write nothing.
        $event->update([
            'price_range' => self::getPriceRange($prices, $currency, $names),
        ]);

        $event->syncSearchIndex();
    }

    /**
     * Make the event's own tier rows match the submitted tiers exactly.
     *
     * Written whether or not the event has shows yet: the event-level set is
     * the one that will survive the move away from per-show copies. Runs under
     * the event row lock taken by handleTickets.
     */
    private static function syncEventTiers(Event $event, $tiers, array $submittedNames, $showIds): void
    {
        $existing = self::where('ticket_type', Event::class)
            ->where('ticket_id', $event->id)
            ->get(['id', 'name']);

        // Delete by primary key, not "type + id + name NOT IN": on an event with
        // no rows yet that range delete takes a gap lock, and two saves of
        // DIFFERENT events sharing the gap would deadlock on their inserts.
        // Which rows survive is decided by the database, with the column's own
        // collation (utf8mb4_unicode_ci on the servers: case, accents and
        // trailing spaces all compare equal; the test database's default
        // collation does not ignore trailing spaces), the
        // same rule the per-show delete uses: renaming "Café" to "cafe" keeps
        // the row on the event exactly as it does on the shows.
        $keptIds = $existing->isEmpty() ? collect() : self::whereIn('id', $existing->pluck('id'))
            ->whereIn('name', $submittedNames)
            ->pluck('id');
        $removedIds = $existing->pluck('id')->diff($keptIds);
        if ($removedIds->isNotEmpty()) {
            self::whereIn('id', $removedIds)->delete();
        }

        if ($tiers->isEmpty()) {
            return;
        }

        // An event whose set is still empty (saved only before the event set
        // existed) takes each tier's name and legacy `type` ('f' free, 'p' pay
        // what you can, still read by the event page but never edited any more)
        // from its latest show's copy, matched with the column's collation like
        // every other match here, which is also where the backfill copies
        // from. The per-show upsert never renames a row, so a "GA" saved as
        // "ga" stays "GA" on the shows and must stay "GA" on the event too.
        // Once the set exists its rows keep their own name and type (the
        // upsert does not update them), so there is nothing to look up.
        $latestShowId = $showIds->first();
        $showCopies = ($existing->isNotEmpty() || $latestShowId === null) ? collect() : $tiers->keys()->mapWithKeys(fn ($name) => [
            (string) $name => self::where('ticket_type', Show::class)
                ->where('ticket_id', $latestShowId)
                ->where('name', (string) $name)
                ->first(['name', 'type']),
        ])->filter();

        $now = now();

        // Same unique key as the per-show rows (tickets_owner_name_unique), so a
        // retried or racing save updates one row instead of adding two.
        self::upsert(
            $tiers->map(fn ($ticketData, $name) => [
                'ticket_type' => Event::class,
                'ticket_id' => $event->id,
                'name' => $showCopies->get((string) $name)?->name ?? (string) $name,
                'description' => $ticketData['description'] ?? '',
                'currency' => $ticketData['currency'] ?? Currency::DEFAULT,
                'ticket_price' => $ticketData['ticket_price'] ?? 0,
                'type' => $showCopies->get((string) $name)?->type ?? 's',
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all(),
            ['ticket_type', 'ticket_id', 'name'],
            ['description', 'currency', 'ticket_price', 'updated_at'],
        );
    }

    /**
     * Drop every tier that a LATER tier's name equals under the tickets.name
     * column collation, so the last one wins, as it already did for exact
     * duplicates. The database itself makes the comparison: only it knows
     * exactly which names its collation treats as equal ("GA" = "ga " =
     * "gá", but "й" <> "и").
     */
    private static function foldCollationDuplicates(array $tickets): array
    {
        $tickets = array_values($tickets);
        $count = count($tickets);
        $collation = self::nameCollation();

        if ($count < 2 || $collation === null) {
            return $tickets;
        }

        $names = array_map(fn ($tier) => (string) ($tier['name'] ?? ''), $tickets);
        $columns = [];
        $bindings = [];
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $columns[] = "(CAST(? AS CHAR) COLLATE {$collation}) = (CAST(? AS CHAR) COLLATE {$collation}) AS p{$i}_{$j}";
                array_push($bindings, $names[$i], $names[$j]);
            }
        }
        $equal = (array) DB::selectOne('SELECT '.implode(', ', $columns), $bindings);

        return array_values(array_filter($tickets, function ($tier, $i) use ($count, $equal) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ((int) $equal["p{$i}_{$j}"] === 1) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH));
    }

    /** The tickets.name collation, or null off MySQL (then only exact names fold). */
    private static function nameCollation(): ?string
    {
        static $collation = false;

        if ($collation === false) {
            $collation = null;
            if (DB::connection()->getDriverName() === 'mysql') {
                $found = DB::selectOne(
                    "SELECT collation_name AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tickets' AND column_name = 'name'"
                )?->c;
                $collation = is_string($found) && preg_match('/^[a-z0-9_]+$/', $found) ? $found : null;
            }
        }

        return $collation;
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
        if (Currency::isValid(Currency::normalize($currency))) {
            return Currency::format($amount, $currency, compact: true);
        }

        return $currency.preg_replace('/\.00$/', '', number_format((float) $amount, 2));
    }
}
