<?php

namespace App\Http\Middleware;

use App\Support\Analytics\Analytics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * One analytics note per view of a public page (Analytics::PAGE_VIEW), when
 * the page_views capture is on. handle() only mints a view id (the page
 * hands it to the time-on-page beacon); the note itself is pushed in
 * terminate(), after PHP-FPM has already sent the page to the visitor, so
 * it adds nothing to the time a page takes.
 *
 * Only the route names below, only GET, only a 200: admin, hosting, the API,
 * redirects and 404s never count. Event pages fill in event_id and
 * organizer_id themselves (request attributes, see EventController).
 */
class RecordPageView
{
    public const PAGES = [
        'home', 'search', 'events.show', 'organizers.show', 'communities.index',
        'communities.show', 'communities.posts.show', 'help', 'privacy', 'terms',
    ];

    public const VIEW_ID = 'analytics.view_id';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')
            && in_array($request->route()?->getName(), self::PAGES, true)
            && Analytics::captures('page_views')
            && ! Analytics::isPrefetch($request)
            && ! Analytics::optedOut($request)) {
            $request->attributes->set(self::VIEW_ID, Str::random(12));
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $viewId = $request->attributes->get(self::VIEW_ID);
        if (! $viewId || $response->getStatusCode() !== 200) {
            return;
        }

        $referrer = Analytics::referrer($request);
        $organizer = $request->route('organizer');

        Analytics::record(Analytics::PAGE_VIEW, [
            'view_id' => $viewId,
            'page' => $request->route()->getName(),
            'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 191),
            'event_id' => $request->attributes->get('analytics.event_id'),
            'organizer_id' => $request->attributes->get('analytics.organizer_id')
                ?? (is_object($organizer) ? $organizer->getKey() : null),
            'source' => $referrer['source'],
            'utm' => Analytics::captures('utm') ? Analytics::campaign($request) : null,
            'props' => array_filter(['ref' => $referrer['ref']]),
        ], $request);
    }
}
