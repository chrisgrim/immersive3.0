<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Curated\Community;
use Carbon\Carbon;
use Illuminate\Http\Response;

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
        $communities = Community::where('status', 'p')->get();
        
        $content = view('sitemaps.index', [
            'events' => $events,
            'organizers' => $organizers,
            'communities' => $communities,
            'lastmod' => Carbon::now()->toIso8601String()
        ]);
        
        return response($content, 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}