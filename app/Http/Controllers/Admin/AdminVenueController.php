<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\MergeVenues;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Venue;
use App\Services\ImageHandler;
use Illuminate\Http\Request;

class AdminVenueController extends Controller
{
    public function index(Request $request)
    {
        return Venue::query()
            ->whereNull('merged_into_id')
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->keywords, fn ($query, $keywords) => $query->where('name', 'like', '%'.addcslashes($keywords, '\\%_').'%'))
            ->orderBy('name')
            ->paginate(20);
    }

    /** A new name moves the address, and the model keeps the old one as a redirect. */
    public function update(Request $request, Venue $venue)
    {
        $this->authorize('update', $venue);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'room' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'directions' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $venue->name) {
            $validated['slug'] = Venue::uniqueSlug($validated['name'], $venue->id);
        }

        $venue->update($validated);

        AuditLog::record($venue, 'venue.updated', $request->user(), ['fields' => array_keys($validated)]);

        return response()->json(['venue' => $venue]);
    }

    /** The venue page's picture, replacing the one before. */
    public function image(Request $request, Venue $venue)
    {
        $this->authorize('update', $venue);

        $request->validate(['image' => ['required', 'image', 'max:10240']]);

        ImageHandler::replaceImage($venue->images()->get(), $request->file('image'), $venue, 800, 600, 'venue-images');

        AuditLog::record($venue, 'venue.image_updated', $request->user());

        return response()->json(['venue' => $venue->refresh()]);
    }

    public function approve(Request $request, Venue $venue)
    {
        $this->authorize('approve', $venue);

        $venue->update(['status' => Venue::APPROVED]);

        AuditLog::record($venue, 'venue.approved', $request->user());

        return response()->json(['venue' => $venue]);
    }

    /** {venue} is the duplicate: it folds into `into_venue_id`. */
    public function merge(Request $request, Venue $venue, MergeVenues $merge)
    {
        $validated = $request->validate(['into_venue_id' => ['required', 'integer', 'exists:venues,id']]);

        return response()->json(['venue' => $merge->handle($venue, Venue::findOrFail($validated['into_venue_id']), $request->user())]);
    }
}
