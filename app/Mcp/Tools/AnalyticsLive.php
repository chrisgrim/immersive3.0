<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\AnswersAnalytics;
use App\Support\Analytics\Analytics;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Moderators only. How many people (not bots) viewed a page on the site in the last 5 minutes, about a minute behind real time. Only counts while live tracking is switched on.')]
class AnalyticsLive extends Tool
{
    use AnswersAnalytics;

    public function handle(Request $request): Response
    {
        if ($denied = $this->moderatorOnly($request)) {
            return $denied;
        }

        if (! Analytics::captures('live')) {
            return Response::error('Live counting is switched off (ei:analytics-capture live on).');
        }

        return Response::json([
            'on_site_now' => app(Analytics::class)->liveCount(5),
            'window_minutes' => 5,
            'note' => 'People with a page view in the last 5 minutes; about a minute behind.',
        ]);
    }
}
