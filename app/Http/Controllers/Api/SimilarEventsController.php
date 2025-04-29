<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SimilarEventsController extends Controller
{
    /**
     * Get similar events for a specific event
     * 
     * @param Event $event
     * @return array
     */
    public function getSimilar(Event $event)
    {
        $cacheKey = "similar_events_{$event->slug}";
        
        // Check for cached results first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            // Set a query timeout to avoid slow queries
            \DB::statement('SET SESSION MAX_EXECUTION_TIME=1000');
            
            $result = [
                'events' => collect([]),
                'isSameCity' => false
            ];
            
            // Event has a location - try to find events in the same city
            if ($event->hasLocation && $event->location) {
                $cityEvents = $this->getEventsByCityQuickly($event);
                
                if ($cityEvents->count() > 0) {
                    // Found events in the same city
                    $result['events'] = $cityEvents;
                    $result['isSameCity'] = true;
                } else {
                    // No events in the same city - prioritize remote events
                    $remoteEvents = $this->getRemoteEvents($event);
                    
                    if ($remoteEvents->count() > 0) {
                        $result['events'] = $remoteEvents;
                    } else {
                        // Fall back to category-based events if no remote events
                        $result['events'] = $this->getEventsByCategoryQuickly($event, 6);
                    }
                }
            } else {
                // Event is remote - get other remote events with same category
                $result['events'] = $this->getEventsByCategoryQuickly($event, 6);
            }
            
            // Store results in cache for 24 hours
            Cache::put($cacheKey, $result, 60 * 60 * 24);
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Error fetching similar events for {$event->id}: " . $e->getMessage());
            return [
                'events' => collect([]),
                'isSameCity' => false
            ];
        }
    }
    
    /**
     * Get events in the same city as the provided event
     * 
     * @param Event $event
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getEventsByCityQuickly(Event $event)
    {
        try {
            // Make sure the event has a location before proceeding
            if (!$event->location || !$event->location->city) {
                return collect([]);
            }
            
            return Event::where('status', 'p')
                ->whereHas('location', function($query) use ($event) {
                    $query->where('city', $event->location->city);
                })
                ->where('id', '!=', $event->id)
                ->with('location')
                ->take(6)
                ->get();
        } catch (\Exception $e) {
            \Log::error("Error in getEventsByCityQuickly: " . $e->getMessage());
            return collect([]);
        }
    }
    
    /**
     * Get events in the same category as the provided event
     * 
     * @param Event $event
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getEventsByCategoryQuickly(Event $event, $limit = 6)
    {
        try {
            return Event::where('status', 'p')
                ->where('id', '!=', $event->id)
                ->where('category_id', $event->category_id)
                ->with('location')
                ->take($limit)
                ->get();
        } catch (\Exception $e) {
            \Log::error("Error in getEventsByCategoryQuickly: " . $e->getMessage());
            return collect([]);
        }
    }
    
    /**
     * Get remote events (events without a physical location)
     * 
     * @param Event $event
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getRemoteEvents(Event $event)
    {
        try {
            return Event::where('status', 'p')
                ->where('id', '!=', $event->id)
                ->where('hasLocation', false)
                ->with('remotelocations')
                ->take(6)
                ->get();
        } catch (\Exception $e) {
            \Log::error("Error in getRemoteEvents: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Get similar events based on location, with fallback to latest remote events
     * 
     * @param Request $request
     * @return array
     */
    public function getSimilarByLocation(Request $request)
    {
        try {
            $lat = (float)$request->get('lat');
            $lng = (float)$request->get('lng');
            $radius = (int)$request->get('radius', 100); // Default 100 mile radius
            
            // If we have coordinates, get events within radius
            if ($lat && $lng) {
                $nearbyEvents = $this->findEventsInRadius($lat, $lng, $radius);
                
                // If we found events nearby, return them
                if ($nearbyEvents->count() > 0) {
                    return [
                        'events' => $nearbyEvents,
                        'title' => 'Similar Events Near You',
                        'isRemote' => false
                    ];
                }
            }
            
            // If no nearby events or no coordinates, fall back to remote events
            $remoteEvents = $this->getLatestRemoteEvents();
            
            return [
                'events' => $remoteEvents,
                'title' => 'Similar Online Events',
                'isRemote' => true
            ];
        } catch (\Exception $e) {
            \Log::error("Error in getSimilarByLocation: " . $e->getMessage());
            return [
                'events' => collect([]),
                'title' => 'Similar Events',
                'isRemote' => true
            ];
        }
    }
    
    /**
     * Find events within a radius from coordinates
     * 
     * @param float $lat
     * @param float $lng
     * @param int $radius
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function findEventsInRadius($lat, $lng, $radius)
    {
        // Create a bounding box for the search (quick filter before distance calculation)
        $latDelta = $radius / 69; // Approximate miles per degree latitude
        $lngDelta = $radius / (69 * cos(deg2rad($lat))); // Approximate miles per degree longitude at this latitude
        
        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;
        
        return Event::where('status', 'p')
            ->where('hasLocation', true)
            ->whereHas('location', function($query) use ($minLat, $maxLat, $minLng, $maxLng) {
                $query->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('latitude', '>=', $minLat)
                    ->where('latitude', '<=', $maxLat)
                    ->where('longitude', '>=', $minLng)
                    ->where('longitude', '<=', $maxLng);
            })
            ->with('location')
            ->take(12)
            ->get();
    }
    
    /**
     * Get latest remote events
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getLatestRemoteEvents()
    {
        return Event::where('status', 'p')
            ->where('hasLocation', false)
            ->orderBy('created_at', 'desc')
            ->with('remotelocations')
            ->take(12)
            ->get();
    }
} 