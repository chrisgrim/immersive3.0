<?php

namespace App\Mcp\Tools;

use App\Actions\Analytics\AnalyticsQuery;
use App\Mcp\Tools\Concerns\AnswersAnalytics;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Moderators only. The top values of one dimension over a period, from the site\'s daily totals (bots excluded): top pages, paths, events, organizers (their own page plus the pages of their events), traffic sources, referring sites, devices, browsers, operating systems, countries, cities or campaign tags, ranked by page views, visits, ticket clicks, searches or nav searches, with average time on page where measured. Use dimension=query with metric searches (places typed in search) or nav_searches (names typed in the nav). Up to 400 days, 50 rows.')]
class AnalyticsTop extends Tool
{
    use AnswersAnalytics;

    public function handle(Request $request): Response
    {
        if ($denied = $this->moderatorOnly($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'dimension' => 'required|in:'.implode(',', [...AnalyticsQuery::DIMENSIONS, 'query']),
            'metric' => 'nullable|in:'.implode(',', array_keys(AnalyticsQuery::METRICS)),
            'days' => 'nullable|integer|min:1|max:'.AnalyticsQuery::MAX_DAYS,
            'limit' => 'nullable|integer|min:1|max:50',
        ]);
        $days = (int) ($validated['days'] ?? 30);
        $metric = $validated['metric'] ?? ($validated['dimension'] === 'query' ? 'searches' : 'page_views');

        return $this->guarded(fn () => app(AnalyticsQuery::class)->top($metric, $validated['dimension'], $days, (int) ($validated['limit'] ?? 20)), $days);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'dimension' => $schema->string()->enum([...AnalyticsQuery::DIMENSIONS, 'query'])->description('What to rank.')->required(),
            'metric' => $schema->string()->enum(array_keys(AnalyticsQuery::METRICS))->description('What to rank by (default page_views; searches for dimension=query).'),
            'days' => $schema->integer()->description('How many days back (1-400, default 30).'),
            'limit' => $schema->integer()->description('Rows (1-50, default 20).'),
        ];
    }
}
