<?php

namespace App\Services\EventScraper\Scrapers;

use App\Services\EventScraper\ScrapedEventData;

interface ScraperInterface
{
    /**
     * Check if this scraper can handle the given URL
     */
    public function canHandle(string $url): bool;

    /**
     * Scrape event data from the URL
     */
    public function scrape(string $url): ScrapedEventData;

    /**
     * Get the priority of this scraper (higher = tried first)
     */
    public function getPriority(): int;
}
