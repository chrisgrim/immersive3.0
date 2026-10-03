<?php

namespace App\Mcp\Tools;

use App\Actions\Analytics\AnalyticsQuery;
use App\Mcp\Tools\Concerns\AnswersAnalytics;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Moderators only. One event\'s or one organizer\'s own numbers over a period, from the daily totals (bots excluded): page views, visits, average time on page, and for an event its ticket clicks, click-through and clicks from search results. Give the event or organizer by slug or id. Up to 400 days.')]
class AnalyticsFor extends Tool
{
    use AnswersAnalytics;

    public function handle(Request $request): Response
    {
        if ($denied = $this->moderatorOnly($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'event' => 'nullable|string|max:191|required_without:organizer',
            'organizer' => 'nullable|string|max:191|required_without:event',
            'days' => 'nullable|integer|min:1|max:'.AnalyticsQuery::MAX_DAYS,
        ]);
        $days = (int) ($validated['days'] ?? 30);
        $kind = isset($validated['event']) ? 'event' : 'organizer';
        $value = $validated[$kind];
        $model = $kind === 'event' ? Event::withoutGlobalScopes()->withTrashed() : Organizer::withoutGlobalScopes();
        $id = ctype_digit($value) ? (int) $value : $model->where('slug', $value)->value('id');

        if (! $id) {
            return Response::error("No {$kind} matches \"{$value}\".");
        }

        return $this->guarded(fn () => app(AnalyticsQuery::class)->for($kind, (int) $id, $days), $days);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->description('An event slug or id (give this or organizer).'),
            'organizer' => $schema->string()->description('An organizer slug or id (give this or event).'),
            'days' => $schema->integer()->description('How many days back (1-400, default 30).'),
        ];
    }
}
