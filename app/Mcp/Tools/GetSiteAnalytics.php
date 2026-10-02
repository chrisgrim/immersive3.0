<?php

namespace App\Mcp\Tools;

use App\Actions\Analytics\SiteAnalyticsReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Moderators only. The site\'s own first-party analytics for the last N days (default 30, max 395), bots left out: totals per kind of activity, the places people search for and which found nothing, the most viewed events with their ticket clicks and click-through, where event page views came from, how often searches lead to a result click and at which position, visitors by country, and how much bot traffic was filtered. Counts only; nothing identifies a person.')]
class GetSiteAnalytics extends Tool
{
    public function handle(Request $request): Response
    {
        if (! $request->user()->isModerator()) {
            return Response::error('Site analytics are for moderators, and need a connection with moderator powers (the mcp:moderate scope).');
        }

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:'.SiteAnalyticsReport::MAX_DAYS,
        ]);

        return Response::json(app(SiteAnalyticsReport::class)->handle((int) ($validated['days'] ?? 30)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('How many days back to report on (1-395, default 30).'),
        ];
    }
}
