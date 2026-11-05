<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\MergeArtists as Merge;
use App\Mcp\Tools\Concerns\AnswersShowActions;
use App\Models\Artist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Moderators only. Fold a duplicate person into the right record. Their credits move across, billed under the old name when the names differ; the duplicate is kept and the move is logged.')]
class MergeArtists extends Tool
{
    use AnswersShowActions;

    public function handle(Request $request): Response
    {
        if (! $request->user()->isModerator()) {
            return Response::error(self::MODERATORS_ONLY);
        }

        $validated = $request->validate([
            'artist_id' => 'required|integer|exists:artists,id',
            'into_artist_id' => 'required|integer|exists:artists,id',
        ]);

        return $this->attempt(fn () => ['artist' => app(Merge::class)->handle(Artist::find($validated['artist_id']), Artist::find($validated['into_artist_id']), $request->user())->only(['id', 'name', 'slug'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'artist_id' => $schema->integer()->description('The duplicate.')->required(),
            'into_artist_id' => $schema->integer()->description('The one to keep.')->required(),
        ];
    }
}
