<?php

namespace App\Actions\Projects;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/** Venues by name or address, each with its address, for the Location step and MCP: only those $user may see. */
class FindVenues
{
    public const LIMIT = 8;

    /** @return Collection<int, array<string, mixed>> */
    public function handle(string $keywords, ?User $user): Collection
    {
        $like = '%'.addcslashes(trim($keywords), '\\%_').'%';

        return Venue::visibleTo($user)
            ->whereNull('merged_into_id')
            ->where('status', '!=', Venue::DRAFT) // a half-added venue is nobody's to pick yet
            ->where(fn ($query) => $query->where('name', 'like', $like)
                ->orWhere('street', 'like', $like)
                ->orWhereRaw("CONCAT_WS(' ', home, street, city) LIKE ?", [$like]))
            ->orderByRaw("status = 'approved' DESC")
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Venue $venue) => $this->present($venue));
    }

    /** The venue already known at this address: the other way round. */
    public function atAddress(?string $home, ?string $street, ?string $city, ?User $user): ?array
    {
        if (blank($street) || blank($city)) {
            return null;
        }

        $venue = Venue::visibleTo($user)
            ->whereNull('merged_into_id')
            ->where('status', '!=', Venue::DRAFT)
            ->where('street', $street)
            ->where('city', $city)
            ->when(filled($home), fn ($query) => $query->where(fn ($q) => $q->where('home', $home)->orWhereNull('home')))
            ->orderByRaw("status = 'approved' DESC")
            ->first();

        return $venue ? $this->present($venue) : null;
    }

    /**
     * For Add a venue: places EI already has that this one might be, by its
     * name or its address, live or still in review (never a draft or a merged
     * one), so the person can claim it instead of adding it twice. Someone
     * else's venue in review shows only its name and city, unless they typed
     * its address; then it comes with a link to see its page for an hour.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function known(?string $name, ?string $home, ?string $street, ?string $city, ?int $except = null, ?User $user = null): Collection
    {
        $name = trim((string) $name);
        $byAddress = filled($street) && filled($city);
        if (mb_strlen($name) < 3 && ! $byAddress) {
            return collect();
        }

        // Which of them are already this person's, in one query.
        $yours = $user ? Venue::keptBy($user)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        return Venue::query()
            ->whereNull('merged_into_id')
            // Live or in review; a draft only when it is this person's own.
            ->where(fn ($status) => $status->whereIn('status', [Venue::APPROVED, Venue::PENDING])
                ->when($user, fn ($q) => $q->orWhere(fn ($own) => $own->where('status', Venue::DRAFT)
                    ->whereIn('id', Venue::keptBy($user)->select('id')))))
            ->when($except, fn ($query) => $query->whereKeyNot($except))
            ->where(fn ($query) => $query
                ->when(mb_strlen($name) >= 3, fn ($q) => $q->orWhere('name', 'like', '%'.addcslashes($name, '\\%_').'%'))
                ->when($byAddress, fn ($q) => $q->orWhere(fn ($at) => $at->where('street', $street)->where('city', $city))))
            ->orderByRaw("status = 'approved' DESC")
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(function (Venue $venue) use ($user, $yours, $byAddress, $home, $street, $city) {
                $mine = $user !== null && in_array($venue->id, $yours, true);
                $same = fn ($a, $b) => mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
                $atAddress = $byAddress && $same($venue->street, $street) && $same($venue->city, $city);
                // Someone else's pending venue opens only to its whole address,
                // number included. The website keeps the number in the street
                // ("340 W 46th St"); the address lookup keeps it in home ("340"),
                // and then home must match too, or street and city alone would
                // hand anyone its exact place.
                // Decided from home ("5th Ave" has a digit but no house number):
                // the number is in the street when home is blank, a place name
                // (no digit), or already part of the street.
                $numberInStreet = blank($venue->home)
                    || ! preg_match('/\d/', (string) $venue->home)
                    // As a whole word: "4" is not in "W 46th St".
                    || preg_match('/(?<![\p{L}\p{N}])'.preg_quote(trim((string) $venue->home), '/').'(?![\p{L}\p{N}])/iu', (string) $venue->street) === 1;
                $wholeAddress = $atAddress && ($numberInStreet || $same($venue->home, $home));

                // Someone else's venue still in review keeps its address to
                // itself (Venue::scopeVisibleTo): only its name and city, and
                // no page link, unless this person typed that very address.
                if ($venue->status !== Venue::APPROVED && ! $mine && ! $wholeAddress) {
                    return [
                        'yours' => false,
                        ...$venue->only(['id', 'name', 'room', 'status', 'city', 'region', 'country']),
                        'address' => trim(implode(', ', array_filter([$venue->city, $venue->region]))),
                        'url' => null,
                        'at_address' => false,
                        'claim_url' => null,
                    ];
                }

                return [
                    // Already this person's: "go to it" rather than "claim it".
                    'yours' => $mine,
                    'edit_url' => $venue->status === Venue::DRAFT ? route('venues.setup', $venue, false) : route('venues.edit', $venue, false),
                    ...$this->present($venue),
                    'at_address' => $atAddress,
                    'claim_url' => $venue->status === Venue::APPROVED
                        ? $venue->publicPath()
                        : URL::temporarySignedRoute('venues.show', now()->addHour(), ['venue' => $venue->slug], false),
                ];
            });
    }

    public function present(Venue $venue): array
    {
        return [
            ...$venue->only(['id', 'slug', 'name', 'room', 'status', 'home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'latitude', 'longitude']),
            // Its own page, for "Is it the same place?" in Add a venue.
            'url' => $venue->publicPath(),
            'address' => trim(implode(', ', array_filter([
                trim(($venue->home !== $venue->street ? $venue->home.' ' : '').$venue->street),
                $venue->city,
                $venue->region,
            ]))),
        ];
    }
}
