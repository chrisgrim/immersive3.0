<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Event;
use App\Models\Organizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /** How long a built sitemap is served before it is rebuilt. */
    public const CACHE_SECONDS = 3600;

    /**
     * Generate XML sitemap.
     *
     * Past events stay listed (Google keeps pages it knows, and old listings
     * still earn search traffic), just at a lower priority than upcoming
     * ones. Built once an hour rather than per request: it reads every
     * published listing.
     */
    public function index()
    {
        $content = Cache::remember('sitemap.xml', self::CACHE_SECONDS, fn () => $this->build());

        return response($content, 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    private function build(): string
    {
        // Published only: an embargoed event already has its final name-based
        // slug, so listing it would announce the show before its embargo lifts.
        // Only the columns the sitemap prints, not the whole row.
        $events = Event::where('status', 'p')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get(['id', 'slug', 'showtype', 'closingDate', 'timezone', 'updated_at']);

        // Still running where the event is, not by the server's clock.
        $now = Carbon::now();
        [$upcomingEvents, $pastEvents] = $events->partition(
            fn (Event $event) => $event->showtype === 'a' || $event->closingAt()?->gte($now)
        );

        $organizers = Organizer::where('status', 'p')
            ->whereHas('events', fn ($query) => $query->where('status', 'p'))
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get(['id', 'slug', 'updated_at']);

        $communities = Community::where('status', 'p')
            ->withMax('posts', 'updated_at')
            ->get(['id', 'slug', 'updated_at']);

        // Exactly the posts PostController::show lets anyone open.
        $posts = Post::query()
            ->without(['community', 'featuredEventImage'])
            ->where('status', 'p')
            ->where('is_hidden', false)
            ->whereHas('community', fn ($query) => $query->where('status', 'p'))
            ->with('community:id,slug')
            ->get(['id', 'slug', 'community_id', 'updated_at']);

        // The category pages robots.txt lets crawlers in on, by the URL the
        // site links to and declares canonical (search/meta.blade.php).
        $categories = Category::query()
            ->whereHas('events', fn ($query) => $query->where('status', 'p'))
            ->orderBy('id')
            ->get(['id']);

        // Home and search surface event listings, so their real freshness
        // signal is the most recent event change — never "now", which makes
        // crawlers distrust lastmod site-wide
        $latestEventUpdate = $events->max('updated_at') ?? Carbon::now();

        return view('sitemaps.index', [
            'upcomingEvents' => $upcomingEvents,
            'pastEvents' => $pastEvents,
            'organizers' => $organizers,
            'communities' => $communities,
            'posts' => $posts,
            'categories' => $categories,
            'lastmod' => $latestEventUpdate->toIso8601String(),
        ])->render();
    }
}
