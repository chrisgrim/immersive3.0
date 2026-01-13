<?php

namespace App\Services\EventScraper;

use App\Services\EventScraper\Scrapers\ScraperInterface;
use App\Services\EventScraper\Scrapers\GenericAIScraper;
use Illuminate\Support\Facades\Log;

/**
 * Main entry point for event scraping.
 *
 * This service manages multiple specialized scrapers and falls back
 * to a generic AI scraper for unknown sites.
 */
class EventScraperService
{
    /** @var ScraperInterface[] */
    private array $scrapers = [];

    public function __construct()
    {
        // Register scrapers in priority order (highest first)
        // Specialized scrapers will be added here:
        // $this->registerScraper(new FeverScraper());
        // $this->registerScraper(new EventbriteScraper());

        // Generic AI scraper as fallback (lowest priority)
        $this->registerScraper(new GenericAIScraper());
    }

    public function registerScraper(ScraperInterface $scraper): void
    {
        $this->scrapers[] = $scraper;

        // Sort by priority (highest first)
        usort($this->scrapers, fn($a, $b) => $b->getPriority() - $a->getPriority());
    }

    /**
     * Scrape event data from a URL
     */
    public function scrape(string $url): ScrapedEventData
    {
        $url = $this->normalizeUrl($url);

        Log::info('EventScraper: Starting scrape', ['url' => $url]);

        foreach ($this->scrapers as $scraper) {
            if ($scraper->canHandle($url)) {
                Log::info('EventScraper: Using scraper', [
                    'url' => $url,
                    'scraper' => get_class($scraper)
                ]);

                try {
                    $result = $scraper->scrape($url);

                    Log::info('EventScraper: Scrape complete', [
                        'url' => $url,
                        'completion' => $result->getCompletionPercentage() . '%',
                        'needs_review' => array_keys($result->getFieldsNeedingReview())
                    ]);

                    return $result;
                } catch (\Exception $e) {
                    Log::error('EventScraper: Scraper failed', [
                        'url' => $url,
                        'scraper' => get_class($scraper),
                        'error' => $e->getMessage()
                    ]);
                    // Continue to next scraper
                }
            }
        }

        // This shouldn't happen if GenericAIScraper is registered
        return new ScrapedEventData(
            sourceUrl: $url,
            scrapedAt: now()->toIso8601String(),
            rawNotes: ['No suitable scraper found']
        );
    }

    /**
     * Scrape multiple URLs and combine the data
     * Useful when event info is spread across main site + ticketing
     */
    public function scrapeMultiple(array $urls): ScrapedEventData
    {
        $results = [];

        foreach ($urls as $url) {
            $results[] = $this->scrape($url);
        }

        return $this->mergeResults($results);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        // Add https if no protocol
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * Merge multiple scrape results, preferring higher confidence data
     */
    private function mergeResults(array $results): ScrapedEventData
    {
        if (empty($results)) {
            return new ScrapedEventData();
        }

        if (count($results) === 1) {
            return $results[0];
        }

        // Start with first result as base
        $merged = $results[0]->toArray();

        // Confidence priority map
        $confidencePriority = ['high' => 3, 'medium' => 2, 'low' => 1, null => 0];

        // For each subsequent result, merge in higher-confidence data
        for ($i = 1; $i < count($results); $i++) {
            $current = $results[$i]->toArray();

            foreach ($current as $key => $value) {
                // Skip null values and empty arrays
                if ($value === null) continue;
                if (is_array($value) && empty($value)) continue;

                // Skip confidence fields themselves
                if (str_ends_with($key, 'Confidence')) continue;

                $confidenceKey = $key . 'Confidence';

                // Special handling for arrays - merge them
                if (is_array($value) && is_array($merged[$key] ?? null)) {
                    $mergedArray = array_merge($merged[$key], $value);
                    // Re-index to ensure sequential keys
                    $merged[$key] = array_values(array_unique($mergedArray));
                    continue;
                }

                // Check if this field has better confidence
                $currentConfidence = $confidencePriority[$current[$confidenceKey] ?? null] ?? 0;
                $mergedConfidence = $confidencePriority[$merged[$confidenceKey] ?? null] ?? 0;

                // If current has higher confidence OR merged is null/empty, use current
                $mergedIsEmpty = $merged[$key] === null || $merged[$key] === '' || $merged[$key] === [];
                if ($mergedIsEmpty || $currentConfidence > $mergedConfidence) {
                    $merged[$key] = $value;
                    if (isset($current[$confidenceKey])) {
                        $merged[$confidenceKey] = $current[$confidenceKey];
                    }
                }
            }

            // Collect all source URLs
            $additionalUrls = $merged['additionalUrls'] ?? [];
            if (is_array($additionalUrls)) {
                $additionalUrls[] = $current['sourceUrl'] ?? null;
                $merged['additionalUrls'] = array_values(array_filter(array_unique($additionalUrls)));
            }
        }

        // Ensure additionalUrls is properly formatted
        if (isset($merged['additionalUrls']) && is_array($merged['additionalUrls'])) {
            $merged['additionalUrls'] = array_values(array_filter($merged['additionalUrls']));
        }

        return new ScrapedEventData(...$merged);
    }
}
