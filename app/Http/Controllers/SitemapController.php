<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use Carbon\Carbon;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate XML sitemap
     */
    public function index()
    {
        $categories = Category::all();
        $events = Event::whereIn('status', ['p', 'e'])->get();
        $organizers = Organizer::has('events')->get();
        
        $content = view('sitemaps.index', [
            'categories' => $categories,
            'events' => $events,
            'organizers' => $organizers,
            'lastmod' => Carbon::now()->toIso8601String()
        ]);
        
        return response($content, 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}