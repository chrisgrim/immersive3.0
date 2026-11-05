<?php

namespace App\Mcp\Tools;

use App\Models\Artist;
use App\Models\AuditLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Propose a person who is not on Everything Immersive yet, after search-artists finds nothing. A moderator approves it; it can be credited straight away.')]
class ProposeArtist extends Tool
{
    public function handle(Request $request): Response
    {
        if (! $request->user()->can('propose', Artist::class)) {
            return Response::error('Artists are for members of an organizer team.');
        }

        $validated = $request->validate(['name' => ['required', 'string', 'max:255', 'regex:/[\p{L}\p{N}]/u']]);

        $artist = Artist::create(['name' => $validated['name'], 'status' => Artist::PROPOSED]);

        AuditLog::record($artist, 'artist.proposed', $request->user());

        return Response::json(['artist' => $artist->only(['id', 'name', 'status'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['name' => $schema->string()->description('The person\'s name.')->required()];
    }
}
