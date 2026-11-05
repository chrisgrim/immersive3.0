<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/** The credits panels' picker: find someone, or propose them for a moderator to approve. */
class ArtistController extends Controller
{
    public function search(Request $request)
    {
        $this->authorize('propose', Artist::class);

        $keywords = trim((string) $request->query('keywords'));

        if ($keywords === '') {
            return response()->json([]);
        }

        return response()->json(Artist::whereNull('merged_into_id')
            ->where('name', 'like', '%'.addcslashes($keywords, '\\%_').'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'status']));
    }

    public function store(Request $request)
    {
        $this->authorize('propose', Artist::class);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255', 'regex:/[\p{L}\p{N}]/u']]);

        $artist = Artist::create(['name' => $validated['name'], 'status' => Artist::PROPOSED]);

        AuditLog::record($artist, 'artist.proposed', $request->user());

        return response()->json(['artist' => $artist->only(['id', 'name', 'status'])], 201);
    }
}
