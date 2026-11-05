<?php

namespace App\Mcp\Tools;

use App\Models\Artist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Find artists by name, for set-credits. Every person is one record: use the one found here rather than proposing a second.')]
class SearchArtists extends Tool
{
    public function handle(Request $request): Response
    {
        if (! $request->user()->can('propose', Artist::class)) {
            return Response::error('Artists are for members of an organizer team.');
        }

        $validated = $request->validate(['keywords' => 'required|string|min:2|max:255']);

        return Response::json(['artists' => Artist::whereNull('merged_into_id')
            ->where('name', 'like', '%'.addcslashes($validated['keywords'], '\\%_').'%')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'status'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['keywords' => $schema->string()->description('Part of the person\'s name.')->required()];
    }
}
