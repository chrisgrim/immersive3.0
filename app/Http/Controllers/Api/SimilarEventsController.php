<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SimilarEventsController extends Controller
{
    public function getSimilar(Event $event)
    {
        $startTime = microtime(true);
        $cacheKey = "similar_events_{$event->slug}";
        
        // Temporarily disable cache while debugging
        // if (Cache::has($cacheKey)) {
        //     $result = Cache::get($cacheKey);
        //     $endTime = microtime(true);
        //     \Log::info("Cached similar events fetch took " . round(($endTime - $startTime) * 1000) . "ms for event {$event->id}");
        //     return $result;
        // }
        
        \Log::info("Generating fresh similar events for event {$event->id} ({$event->slug})");
        
        try {
            // Set a query timeout to avoid extremely slow queries
            \DB::statement('SET SESSION MAX_EXECUTION_TIME=1000'); // 1000ms (1 second) timeout
            
            $queryStartTime = microtime(true);
            
            // Simplify the query to make it faster
            if ($event->hasLocation) {
                // Get city-based events first
                $similarEvents = $this->getEventsByCityQuickly($event);
                
                // If not enough, add category-based events
                if ($similarEvents->count() < 3) {
                    $additionalEvents = $this->getEventsByCategoryQuickly($event, 6 - $similarEvents->count());
                    $similarEvents = $similarEvents->merge($additionalEvents);
                }
            } else {
                // For remote events, just get by category
                $similarEvents = $this->getEventsByCategoryQuickly($event, 6);
            }
            
            $queryEndTime = microtime(true);
            $queryTime = round(($queryEndTime - $queryStartTime) * 1000);

            
            // Temporarily disable caching
            // Cache::put($cacheKey, $similarEvents, 60 * 60 * 24);
            
            return $similarEvents;
        } catch (\Exception $e) {
            // Log the error
            \Log::error("Error fetching similar events for {$event->id} ({$event->slug}): " . $e->getMessage());
            
            // Return empty collection as fallback
            return collect([]);
        }
    }
    
    protected function getEventsByCityQuickly(Event $event)
    {
        try {
            // More optimized query for city-based events
            $results = Event::where('status', 'p')
                ->whereHas('location', function($query) use ($event) {
                    $query->where('city', $event->location->city);
                })
                ->where('id', '!=', $event->id)
                ->take(6)
                ->get();
            
            
            return $results;
        } catch (\Exception $e) {
            \Log::error("Error in getEventsByCityQuickly: " . $e->getMessage());
            return collect([]);
        }
    }
    
    protected function getEventsByCategoryQuickly(Event $event, $limit = 6)
    {
        try {
            // More optimized query for category-based events
            $results = Event::where('status', 'p')
                ->where('id', '!=', $event->id)
                ->where('category_id', $event->category_id)
                ->take($limit)
                ->get();
            
            
            return $results;
        } catch (\Exception $e) {
            \Log::error("Error in getEventsByCategoryQuickly: " . $e->getMessage());
            return collect([]);
        }
    }
} 