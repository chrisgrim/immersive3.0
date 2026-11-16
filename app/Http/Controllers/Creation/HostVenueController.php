<?php

namespace App\Http\Controllers\Creation;

use App\Actions\Projects\UpdateVenue;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Venue;
use App\Support\PhotoGallery;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
                'draft' => $venue->status === Venue::DRAFT,
                'owned' => (int) $venue->user_id === (int) $user->id,
                'image' => $venue->largeImagePath ?? $venue->images->first()?->large_image_path,
            ]);

        return view('venues.index', ['venues' => $venues]);
    }

    /** Add a venue: the steps, from the start. The venue is made at the Location step. */
    public function create()
    {
        $this->authorize('propose', Venue::class);

        return view('venues.create', ['pageVenue' => null]);
    }

    /**
     * Add a venue, step 1: a draft with its name, kept by whoever adds it (their
     * organization, or themselves), as an event draft is made at "New event".
     * It reaches EI only when the steps are finished (submit).
     */
    public function store(Request $request)
    {
        $this->authorize('propose', Venue::class);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/[\p{L}\p{N}]/u'],
            'room' => ['nullable', 'string', 'max:255'],
        ]);

        $venue = Venue::create([...$validated, 'status' => Venue::DRAFT]);
        $user = $request->user();
        $team = $user->getCurrentOrganizer();
        if ($team && $user->belongsToOrganization($team)) {
            $venue->organizer_id = $team->id;
        } else {
            $venue->user_id = $user->id;
        }
        $venue->save();

        AuditLog::record($venue, 'venue.drafted', $user);

        return response()->json($this->present($venue), 201);
    }

    /**
     * Delete a draft: whoever is adding it (or staff) may. A venue sent to EI
     * or live is not deleted here: listings may point at it.
     */
    public function destroy(Request $request, Venue $venue)
    {
        // Checked again under a lock on the row: a submit crossing this one
        // wins, and a venue sent to EI is never deleted here.
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $venue) {
            $locked = Venue::whereKey($venue->id)->lockForUpdate()->first();
            abort_unless($locked?->status === Venue::DRAFT, 422, 'Only a draft can be deleted. Ask EI to remove a venue that was sent or is live.');

            foreach ($locked->images as $image) {
                try {
                    \App\Services\ImageHandler::deleteImage($image);
                } catch (\Throwable $e) {
                    report($e);
                    $image->delete();
                }
            }
            AuditLog::record($locked, 'venue.draft_deleted', $request->user());
            $locked->delete();
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Deleted.'])
            : redirect()->route('hosting.venues');
    }

    /** The last step: a finished draft goes to EI for approval. */
    public function submit(Request $request, Venue $venue)
    {
        if ($venue->status !== Venue::DRAFT) {
            return response()->json($this->present($venue));
        }

        if (blank($venue->name) || blank($venue->city) || $venue->latitude === null || $venue->longitude === null) {
            throw ValidationException::withMessages(['venue' => 'Add the venue\'s address (the Location step) before sending it.']);
        }

        $venue->update(['status' => Venue::PENDING]);
        // As a venue proposed with an event: its team sees it while it waits (Venue::visibleTo).
        AuditLog::record($venue, 'venue.proposed', $request->user());

        return response()->json($this->present($venue->refresh()));
    }

    /** Add a venue, carried on: the draft's remaining steps. */
    public function setup(Venue $venue)
    {
        return view('venues.create', ['pageVenue' => $this->present($venue)]);
    }

    public function edit(Venue $venue)
    {
        return view('venues.edit', ['pageVenue' => $this->present($venue)]);
    }

    public function update(Request $request, Venue $venue, UpdateVenue $update)
    {
        UpdateVenue::prepare($request);

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
            'amenities' => $venue->amenities ?? [],
            // A new name waiting for EI's approval.
            'pending_name' => $venue->nameChangeRequests()->where('status', 'pending')->value('requested_name'),
            // EI's staff rename straight away, so their form starts from the real name.
            'renames_directly' => (bool) auth()->user()?->isModerator(),
            'url' => $venue->publicPath(),
            'image' => $venue->images()->orderBy('rank')->value('large_image_path') ?? $venue->largeImagePath,
            'photos' => PhotoGallery::present($venue),
            'max_photos' => self::MAX_PHOTOS,
            'owner' => $venue->owner?->only(['id', 'name']),
        ];
    }
}
