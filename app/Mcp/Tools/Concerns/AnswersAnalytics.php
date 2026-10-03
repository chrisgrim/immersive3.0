<?php

namespace App\Mcp\Tools\Concerns;

use App\Actions\Analytics\AnalyticsQuery;
use Illuminate\Database\QueryException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Shared by the analytics-* MCP tools: moderators only (the credential must
 * carry mcp:moderate, see User::isModerator), and every answer comes with
 * the period, the definitions, and the note on visitor text.
 */
trait AnswersAnalytics
{
    private function moderatorOnly(Request $request): ?Response
    {
        return $request->user()->isModerator() ? null
            : Response::error('Site analytics are for moderators, and need a connection with moderator powers (the mcp:moderate scope).');
    }

    private function answer(array $data, int $days): Response
    {
        return Response::json([
            'period' => ['days' => max(1, min(AnalyticsQuery::MAX_DAYS, $days)), 'timezone' => 'UTC'],
            'definitions' => AnalyticsQuery::DEFINITIONS,
            'data' => $data,
        ]);
    }

    /** A query over its time budget says so instead of failing the call. */
    private function guarded(\Closure $query, int $days): Response
    {
        try {
            return $this->answer($query(), $days);
        } catch (QueryException $e) {
            report($e);

            return Response::error('That question took too long to answer. Ask about fewer days or a narrower breakdown.');
        }
    }
}
