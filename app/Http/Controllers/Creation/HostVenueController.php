<?php

namespace App\Http\Controllers\Creation;

use App\Actions\Projects\UpdateVenue;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Venue;
use App\Support\PhotoGallery;
use Illuminate\Http\Request;

/**
 * The venue's own page, edited by the team that keeps it, or by staff.
 * Routes carry can:update,venue.
 */
class HostVenueController extends Controller
{
    /** The venue page's grid holds four. */
    public const MAX_PHOTOS = 4;

    /** Your venues: every venue you keep, in long rows like the hosting list, 20 a page. */
    public function index(Request $request)
    {
        $user = $request->user();

        $venues = Venue::keptBy($user)
            ->orderBy('name')
            ->with(['images' => fn ($images) => $images->orderBy('rank')])
            ->paginate(20, ['id', 'name', 'slug', 'city', 'region', 'status', 'largeImagePath', 'user_id'])
            ->fragment('venues')
            ->through(fn (Venue $venue) => [
                'name' => $venue->name,
                'slug' => $venue->slug,
                'place' => collect([$venue->city, $venue->region])->filter()->join(', '),
                'pending' => $venue->status === 'pending',
                'owned' => (int) $venue->user_id === (int) $user->id,
                'image' => $venue->largeImagePath ?? $venue->images->first()?->large_image_path,
            ]);

        return view('venues.index', ['venues' => $venues]);
    }

    public function edit(Venue $venue)
    {
        return view('venues.edit', ['pageVenue' => $this->present($venue)]);
    }

    public function update(Request $request, Venue $venue, UpdateVenue $update)
    {
        return response()->json($this->present($update->handle($venue, $request->validate(UpdateVenue::RULES, UpdateVenue::MESSAGES), $request->user())));
    }

    /** Up to four photos, saved as an event's are (PhotoGallery). Landscape, as the page shows them. */
    public function photos(Request $request, Venue $venue)
    {
        $request->validate(PhotoGallery::rules(self::MAX_PHOTOS));

        PhotoGallery::sync($venue, $request, 'venue-images', fn () => [1200, 800], self::MAX_PHOTOS);

        AuditLog::record($venue, 'venue.photos_updated', $request->user());

        return response()->json($this->present($venue->refresh()));
    }

    private function present(Venue $venue): array
    {
        return [
            ...$venue->only([
                'id', 'slug', 'name', 'room', 'description', 'directions', 'status',
                'home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'latitude', 'longitude',
                'website', 'email', 'phone', 'instagramHandle', 'facebookHandle', 'twitterHandle', 'tiktokHandle',
            ]),
            'url' => $venue->publicPath(),
            'image' => $venue->images()->orderBy('rank')->value('large_image_path') ?? $venue->largeImagePath,
            'photos' => PhotoGallery::present($venue),
            'max_photos' => self::MAX_PHOTOS,
            'owner' => $venue->owner?->only(['id', 'name']),
        ];
    }
}
