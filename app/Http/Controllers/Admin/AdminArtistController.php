<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\MergeArtists;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminArtistController extends Controller
{
    private const RULES = [
        'name' => ['sometimes', 'required', 'string', 'max:255'],
        'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
    ];

    public function index(Request $request)
    {
        return Artist::query()
            ->whereNull('merged_into_id')
            ->withCount('credits')
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->keywords, fn ($query, $keywords) => $query->where('name', 'like', '%'.addcslashes($keywords, '\\%_').'%'))
            ->orderBy('name')
            ->paginate(20);
    }

    public function show(Artist $artist)
    {
        return response()->json(['artist' => $artist->loadCount('credits')]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255'], 'bio' => self::RULES['bio']]);

        $artist = Artist::create([...$validated, 'status' => Artist::APPROVED]);

        AuditLog::record($artist, 'artist.created', $request->user());

        return response()->json(['artist' => $artist], 201);
    }

    /** No address changes: artists have no public page yet. */
    public function update(Request $request, Artist $artist)
    {
        $this->authorize('update', $artist);

        $artist->update($request->validate(self::RULES));

        AuditLog::record($artist, 'artist.updated', $request->user());

        return response()->json(['artist' => $artist]);
    }

    /** Only someone with no credits: the way to fold a duplicate away is a merge, which keeps them. */
    public function destroy(Request $request, Artist $artist)
    {
        $this->authorize('delete', $artist);

        if ($artist->credits()->exists()) {
            throw ValidationException::withMessages(['artist' => 'This artist has credits. Merge them into the right record instead.']);
        }

        AuditLog::record($artist, 'artist.deleted', $request->user(), ['name' => $artist->name]);
        $artist->delete();

        return response()->json(['message' => 'Artist deleted.']);
    }

    public function approve(Request $request, Artist $artist)
    {
        $this->authorize('approve', $artist);

        $artist->update(['status' => Artist::APPROVED]);

        AuditLog::record($artist, 'artist.approved', $request->user());

        return response()->json(['artist' => $artist]);
    }

    /** {artist} is the duplicate: it folds into `into_artist_id`. */
    public function merge(Request $request, Artist $artist, MergeArtists $merge)
    {
        $validated = $request->validate(['into_artist_id' => ['required', 'integer', 'exists:artists,id']]);

        return response()->json(['artist' => $merge->handle($artist, Artist::findOrFail($validated['into_artist_id']), $request->user())]);
    }
}
