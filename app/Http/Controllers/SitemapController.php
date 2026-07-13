<?php

namespace App\Http\Controllers;

use App\Models\Curated\Community;
use App\Models\Event;
use App\Models\Organizer;
use Carbon\Carbon;

class SitemapController extends Controller
{
    /**
     * Generate XML sitemap
     */
    public function index()
    {
        $events = Event::whereIn('status', ['p', 'e'])
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get();
        $organizers = Organizer::has('events')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get();
        $communities = Community::where('status', 'p')
            ->withMax('posts', 'updated_at')
            ->get();

        // Home and search surface event listings, so their real freshness
        // signal is the most recent event change — never "now", which makes
        // crawlers distrust lastmod site-wide
        $latestEventUpdate = $events->max('updated_at') ?? Carbon::now();

        $content = view('sitemaps.index', [
            'events' => $events,
            'organizers' => $organizers,
            'communities' => $communities,
            'lastmod' => $latestEventUpdate->toIso8601String(),
        ]);

        return response($content, 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
