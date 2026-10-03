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
#[Description('Moderators only. The site\'s own traffic over time from its daily totals (bots excluded): page views, visits (also confirmed_visits: browser confirmed, and engaged_visits, side by side with visits; measured only from measured_since on, each point ending with visits_on_measured_days, the base to compare them with), searches, ticket clicks or nav searches per day (per week beyond 90 days), optionally split by the top values of one dimension. Page views and the visit metrics split by any dimension; searches, ticket clicks and nav searches only by country, device, browser, os or city (ticket clicks also by event); an unsupported pair is refused. Up to 400 days.')]
class AnalyticsTrend extends Tool
{
    use AnswersAnalytics;

    public function handle(Request $request): Response
    {
        if ($denied = $this->moderatorOnly($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'metric' => 'required|in:'.implode(',', array_keys(AnalyticsQuery::METRICS)),
            'dimension' => 'nullable|in:'.implode(',', AnalyticsQuery::DIMENSIONS),
            'days' => 'nullable|integer|min:1|max:'.AnalyticsQuery::MAX_DAYS,
        ]);
        $days = (int) ($validated['days'] ?? 30);

        if ($refusal = AnalyticsQuery::unsupported($validated['metric'], $validated['dimension'] ?? null)) {
            return Response::error($refusal);
        }

        return $this->guarded(fn () => app(AnalyticsQuery::class)->trend($validated['metric'], $validated['dimension'] ?? null, $days), $days);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()->enum(array_keys(AnalyticsQuery::METRICS))->description('What to count.')->required(),
            'dimension' => $schema->string()->enum(AnalyticsQuery::DIMENSIONS)->description('Optional: split the series by the top 8 values of this.'),
            'days' => $schema->integer()->description('How many days back (1-400, default 30).'),
        ];
    }
}
