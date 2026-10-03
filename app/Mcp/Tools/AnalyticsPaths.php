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
#[Description('Moderators only. Paths through the site: for a page path such as "/" or "/index/search" or "/events/some-slug", where people went next or came from before it, from the daily totals. Only steps at least 5 people took on a day are kept, so rare paths do not appear. Up to 400 days.')]
class AnalyticsPaths extends Tool
{
    use AnswersAnalytics;

    public function handle(Request $request): Response
    {
        if ($denied = $this->moderatorOnly($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:191', 'regex:/^\//'],
            'direction' => 'nullable|in:next,previous',
            'days' => 'nullable|integer|min:1|max:'.AnalyticsQuery::MAX_DAYS,
        ]);
        $days = (int) ($validated['days'] ?? 30);

        return $this->guarded(fn () => app(AnalyticsQuery::class)->paths($validated['path'], $validated['direction'] ?? 'next', $days), $days);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('A page path starting with "/", no query string.')->required(),
            'direction' => $schema->string()->enum(['next', 'previous'])->description('Where people went next (default) or came from.'),
            'days' => $schema->integer()->description('How many days back (1-400, default 30).'),
        ];
    }
}
