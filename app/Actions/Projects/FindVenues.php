<?php

namespace App\Actions\Projects;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Collection;

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
            ->where('street', $street)
            ->where('city', $city)
            ->when(filled($home), fn ($query) => $query->where(fn ($q) => $q->where('home', $home)->orWhereNull('home')))
            ->orderByRaw("status = 'approved' DESC")
            ->first();

        return $venue ? $this->present($venue) : null;
    }

    /** @return array<string, mixed> */
    public function present(Venue $venue): array
    {
        return [
            ...$venue->only(['id', 'name', 'room', 'status', 'home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'latitude', 'longitude']),
            'address' => trim(implode(', ', array_filter([
                trim(($venue->home !== $venue->street ? $venue->home.' ' : '').$venue->street),
                $venue->city,
                $venue->region,
            ]))),
        ];
    }
}
