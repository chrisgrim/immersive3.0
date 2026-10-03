<?php

namespace App\Mcp\Tools;

use App\Actions\Analytics\SearchConsoleReport;
use App\Mcp\Tools\Concerns\AnswersAnalytics;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Moderators only. Google Search Console numbers for the site (imported nightly; Google reports 2 to 3 days late): how often the site showed up in Google results (impressions), how often people clicked through (clicks), the click-through rate and the average position. report=totals (the period, the period before, and a day by day series, weekly past 90 days), queries (the Google searches that led here), pages (which pages Google sent people to, with the event or organizer each one is), countries, devices, or query_pages (searches and the pages they led to: pass query for one search, page for one page, or both for that one pair). query and page also filter the queries and pages reports (text contained). Rows are the most clicked unless order=impressions (the most shown, e.g. searches where the site shows up but nobody clicks). Pages can add up to more than the totals (Google counts each page shown). Up to 480 days (Google keeps 16 months), 100 rows.')]
class SearchConsole extends Tool
{
    use AnswersAnalytics;

    private const REPORTS = ['totals', 'queries', 'pages', 'countries', 'devices', 'query_pages'];

    public function handle(Request $request): Response
    {
        if ($denied = $this->moderatorOnly($request)) {
            return $denied;
        }

        $report = app(SearchConsoleReport::class);
        if (! $report->configured()) {
            return Response::error('Google Search Console is not configured on this server yet, so there are no Google search numbers.');
        }

        $validated = $request->validate([
            'report' => 'required|in:'.implode(',', self::REPORTS),
            'days' => 'nullable|integer|min:1|max:'.SearchConsoleReport::MAX_DAYS,
            'limit' => 'nullable|integer|min:1|max:100',
            'query' => 'nullable|string|max:191',
            'page' => 'nullable|string|max:500',
            'order' => 'nullable|in:clicks,impressions',
        ]);
        $days = (int) ($validated['days'] ?? 28);
        $limit = (int) ($validated['limit'] ?? 25);
        $query = $validated['query'] ?? null;
        $page = $validated['page'] ?? null;
        $order = $validated['order'] ?? 'clicks';

        try {
            $data = match ($validated['report']) {
                'totals' => $report->totals($days),
                'queries' => $this->wrapQueries($report->queries($days, $limit, $query, $order)),
                'pages' => array_map(fn ($row) => $this->page($row), $report->pages($days, $limit, $page, $order)),
                'countries' => $report->countries($days, $limit),
                'devices' => $report->devices($days),
                'query_pages' => $this->wrapQueries($report->queryPages($days, $limit, $query, $page, $order)),
            };
        } catch (QueryException $e) {
            report($e);

            return Response::error('That question took too long to answer. Ask about fewer days or fewer rows.');
        }

        // The period the answer was built for (same read as the rows).
        $period = $report->lastPeriod();

        return Response::json([
            'period' => $period ?? 'none yet: nothing has been imported from Google so far',
            'data_since' => $period['data_since'] ?? null,
            'timezone' => 'America/Los_Angeles (Google\'s own days)',
            'data_lag' => SearchConsoleReport::LAG_NOTE,
            'definitions' => SearchConsoleReport::DEFINITIONS,
            'data' => $data,
        ]);
    }

    /**
     * What people typed into Google is visitor text, and so is an address
     * Google indexed (its query string can carry anything): wrapped,
     * cleaned and capped.
     */
    private function wrapQueries(array $rows): array
    {
        return array_map(fn ($row) => ['query' => $this->visitorText($row['query'], 100)]
            + (isset($row['page']) ? ['page' => $this->visitorText($row['page'], 191)] : [])
            + $row, $rows);
    }

    private function visitorText(?string $text, int $max): array
    {
        return ['visitor_text' => mb_substr(trim(preg_replace('/[\p{C}\x{E0000}-\x{E007F}]+/u', ' ', (string) $text) ?? ''), 0, $max)];
    }

    /** A page row for an assistant: the address, and the event or organizer it is. */
    private function page(array $row): array
    {
        $what = match ($row['kind']) {
            'event' => ['event' => ['id' => $row['id'], 'name' => $row['name']]],
            // An event or organizer address that matches none now.
            'gone' => ['gone' => true],
            'organizer' => ['organizer' => ['id' => $row['id'], 'name' => $row['name']]],
            default => [],
        };

        // A current event or organizer path is ours; any other address
        // (gone ones, and anything with a query string) is wrapped.
        $ours = in_array($row['kind'], ['event', 'organizer'], true) && str_starts_with($row['page'], '/') && ! str_contains($row['page'], '?');

        return ['page' => $ours ? $row['page'] : $this->visitorText($row['page'], 191)] + $what + array_intersect_key($row, array_flip(['clicks', 'impressions', 'ctr', 'position']));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'report' => $schema->string()->enum(self::REPORTS)->description('What to answer.')->required(),
            'days' => $schema->integer()->description('How many days, ending on the newest imported day (1-480, default 28).'),
            'limit' => $schema->integer()->description('Rows (1-100, default 25).'),
            'query' => $schema->string()->description('query_pages: one Google search (exact); with page too, just that search and page pair. queries: only searches containing this.'),
            'page' => $schema->string()->description('query_pages: one page, as a path like /events/some-slug or a full address (exact); with query too, just that pair. pages: only pages containing this.'),
            'order' => $schema->string()->enum(['clicks', 'impressions'])->description('queries, pages, query_pages: the most clicked rows (default) or the most shown.'),
        ];
    }
}
