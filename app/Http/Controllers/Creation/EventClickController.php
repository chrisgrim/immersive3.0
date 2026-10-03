<?php

namespace App\Http\Controllers\Creation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\TrackClick;
use App\Support\Analytics\Analytics;
use Illuminate\Support\Facades\Cache;

class EventClickController extends Controller
{
    /**
     * Track a click on an event
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $eventId
     * @return \Illuminate\Http\Response
     */
    public function trackClick(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        // Strings only: an array here (click_type[]=x) used to 500.
        $clickType = is_string($request->input('click_type')) ? mb_substr($request->input('click_type'), 0, 50) : 'link';
        $destination = is_string($request->input('destination_url')) ? $request->input('destination_url') : null;

        $record = fn () => Analytics::record(Analytics::TICKET_CLICK, [
            'event_id' => $event->id,
            'source' => mb_substr($clickType, 0, 16),
        ], $request);

        // Bots are recorded (flagged, like every other row) but left out of
        // the organizer's click count (track_clicks).
        if (Analytics::looksLikeBot($request)) {
            $record();

            return response()->json(['success' => true]);
        }

        // Simple rate limiting and duplicate prevention. The IP and user
        // agent are only hashed into this short-lived cache key, never stored.
        $userAgent = (string) $request->userAgent();
        $cacheKey = "event_click:{$eventId}:".md5($request->ip().'|'.$userAgent);

        // If this exact click was recorded in the last 5 minutes, don't
        // record it again, here or in analytics.
        if (Cache::has($cacheKey)) {
            return response()->json(['success' => true]);
        }

        // Cache this click to prevent duplicates in short time window
        Cache::put($cacheKey, true, now()->addMinutes(5));

        $record();

        TrackClick::create([
            'event_id' => $event->id,
            'organizer_id' => $event->organizer_id,
            'user_id' => auth()->id(), // Will be null for guest users
            'referer_url' => substr($request->header('referer', ''), 0, 255),
            'destination_url' => substr((string) ($destination ?? $event->ticketUrl ?? $event->websiteUrl ?? $event->organizer->website), 0, 255),
            'click_type' => $clickType,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Get click statistics for an event
     *
     * @param  int  $eventId
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getStats($eventId, Request $request)
    {
        // Ensure the user has permission to view these stats (event owner, organizer, or admin)
        $event = Event::findOrFail($eventId);
        
        $this->authorize('viewClickStats', $event);
        
        // Determine if detailed stats should be included (admin users or explicit request)
        $includeDetailed = $request->has('detailed') || 
                         (auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isModerator()));
        
        $stats = [
            'total' => TrackClick::where('event_id', $eventId)->count(),
        ];
        
        // Include detailed stats for admins or when explicitly requested
        if ($includeDetailed) {
            $stats = array_merge($stats, [
                'unique_users' => TrackClick::where('event_id', $eventId)
                    ->whereNotNull('user_id')
                    ->distinct('user_id')
                    ->count('user_id'),
            ]);
        }
        
        // Always include daily stats
        $stats['daily'] = TrackClick::where('event_id', $eventId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        return response()->json($stats);
    }
}
