<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\MergeVenues as Merge;
use App\Mcp\Tools\Concerns\AnswersShowActions;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Moderators only. Fold a duplicate venue into the right one. Its listings and old address move across; the duplicate is kept and the move is logged.')]
class MergeVenues extends Tool
{
    use AnswersShowActions;

    public function handle(Request $request): Response
    {
        if (! $request->user()->isModerator()) {
            return Response::error(self::MODERATORS_ONLY);
        }

        $validated = $request->validate([
            'venue_id' => 'required|integer|exists:venues,id',
            'into_venue_id' => 'required|integer|exists:venues,id',
        ]);

        return $this->attempt(fn () => ['venue' => app(Merge::class)->handle(Venue::find($validated['venue_id']), Venue::find($validated['into_venue_id']), $request->user())->only(['id', 'name', 'slug'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'venue_id' => $schema->integer()->description('The duplicate.')->required(),
            'into_venue_id' => $schema->integer()->description('The one to keep.')->required(),
        ];
    }
}
