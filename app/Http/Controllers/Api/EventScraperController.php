<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Services\EventScraper\EventScraperService;
use App\Services\EventScraper\ScrapedEventData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventScraperController extends Controller
{
    public function __construct(
        private EventScraperService $scraperService
    ) {}

    /**
     * Scrape event data from one or more URLs
     *
     * POST /api/scraper/extract
     * {
     *     "url": "https://example.com/event",
     *     // OR for multiple sources:
     *     "urls": ["https://example.com/event", "https://ticketing.com/event"]
     * }
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required_without:urls|url',
            'urls' => 'required_without:url|array',
            'urls.*' => 'url',
        ]);

        $urls = $request->input('urls') ?? [$request->input('url')];

        // Filter out empty URLs
        $urls = array_filter($urls, fn($url) => !empty(trim($url)));
        $urls = array_values($urls); // Re-index array

        if (empty($urls)) {
            return response()->json([
                'success' => false,
                'error' => 'At least one valid URL is required'
            ], 422);
        }

        try {
            // Increase PHP timeout for multiple URLs
            set_time_limit(count($urls) * 60 + 60);

            if (count($urls) === 1) {
                $result = $this->scraperService->scrape($urls[0]);
            } else {
                $result = $this->scraperService->scrapeMultiple($urls);
            }

            // Check for duplicate events and organizers
            $duplicates = $this->checkForDuplicates($result);

            return response()->json([
                'success' => true,
                'data' => $result->toArray(),
                'meta' => [
                    'completion_percentage' => $result->getCompletionPercentage(),
                    'fields_needing_review' => $result->getFieldsNeedingReview(),
                    'urls_processed' => $urls,
                ],
                'duplicates' => $duplicates,
            ]);
        } catch (\Exception $e) {
            \Log::error('EventScraper: Extract failed', [
                'urls' => $urls,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Scraping failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if the scraped event or organizer already exists
     */
    private function checkForDuplicates(ScrapedEventData $data): array
    {
        $duplicates = [
            'events' => [],
            'organizers' => [],
        ];

        // Check for duplicate events by name
        if ($data->name) {
            $matchingEvents = Event::whereRaw('LOWER(name) = ?', [strtolower($data->name)])
                ->select('id', 'name', 'slug', 'status')
                ->withoutGlobalScopes()
                ->get()
                ->map(fn($event) => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'url' => "/events/{$event->slug}",
                    'status' => $event->status,
                ]);

            $duplicates['events'] = $matchingEvents->toArray();
        }

        // Check for duplicate organizers by name
        if ($data->organizerName) {
            $matchingOrganizers = Organizer::whereRaw('LOWER(name) = ?', [strtolower($data->organizerName)])
                ->select('id', 'name', 'slug', 'status')
                ->withoutGlobalScopes()
                ->get()
                ->map(fn($org) => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'url' => "/organizers/{$org->slug}",
                    'status' => $org->status,
                ]);

            $duplicates['organizers'] = $matchingOrganizers->toArray();
        }

        return $duplicates;
    }

    /**
     * Quick test endpoint - just pass URL as query param
     *
     * GET /api/scraper/test?url=https://example.com/event
     */
    public function test(Request $request): JsonResponse
    {
        $url = $request->query('url');

        if (!$url) {
            return response()->json([
                'error' => 'Missing url parameter',
                'usage' => 'GET /api/scraper/test?url=https://example.com/event'
            ], 400);
        }

        $result = $this->scraperService->scrape($url);

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [
                'completion_percentage' => $result->getCompletionPercentage(),
                'fields_needing_review' => $result->getFieldsNeedingReview(),
            ]
        ]);
    }
}
