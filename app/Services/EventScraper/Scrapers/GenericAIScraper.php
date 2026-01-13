<?php

namespace App\Services\EventScraper\Scrapers;

use App\Services\EventScraper\ScrapedEventData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generic AI-powered scraper that works with any website.
 * Uses Claude (Anthropic) or OpenAI to extract structured event data from raw HTML.
 *
 * Set ANTHROPIC_API_KEY in .env to use Claude (preferred)
 * Falls back to OPENAI_API_KEY if Anthropic not available
 */
class GenericAIScraper implements ScraperInterface
{
    private ?string $anthropicApiKey;
    private ?string $openaiApiKey;
    private string $provider;

    public function __construct()
    {
        $this->anthropicApiKey = config('services.anthropic.api_key') ?? env('ANTHROPIC_API_KEY');
        $this->openaiApiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY');

        // Prefer Claude if available
        $this->provider = $this->anthropicApiKey ? 'anthropic' : 'openai';
    }

    public function canHandle(string $url): bool
    {
        // Generic scraper can attempt any URL
        return true;
    }

    public function getPriority(): int
    {
        // Lowest priority - used as fallback
        return 0;
    }

    public function scrape(string $url): ScrapedEventData
    {
        // Fetch the page content
        $html = $this->fetchPage($url);

        if (!$html) {
            return new ScrapedEventData(
                sourceUrl: $url,
                scrapedAt: now()->toIso8601String(),
                scraperUsed: 'generic_ai',
                rawNotes: ['Failed to fetch page content']
            );
        }

        // Extract images before cleaning HTML
        $images = $this->extractImages($html, $url);

        // Clean and truncate HTML for AI processing
        $cleanedContent = $this->cleanHtml($html);

        // Extract data using AI
        $extractedData = $this->extractWithAI($cleanedContent, $url, $images);

        return $this->buildScrapedEventData($extractedData, $url);
    }

    /**
     * Extract image URLs from HTML before stripping tags
     */
    private function extractImages(string $html, string $baseUrl): array
    {
        $images = [];

        // Extract from <img> tags
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $imgMatches);
        if (!empty($imgMatches[1])) {
            $images = array_merge($images, $imgMatches[1]);
        }

        // Extract from og:image meta tags (often the best quality)
        preg_match_all('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $ogMatches);
        if (!empty($ogMatches[1])) {
            $images = array_merge($ogMatches[1], $images); // Prepend og:image as it's usually best
        }

        // Also check reverse order meta tags
        preg_match_all('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'][^>]*>/i', $html, $ogMatches2);
        if (!empty($ogMatches2[1])) {
            $images = array_merge($ogMatches2[1], $images);
        }

        // Extract from twitter:image
        preg_match_all('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $twitterMatches);
        if (!empty($twitterMatches[1])) {
            $images = array_merge($twitterMatches[1], $images);
        }

        // Clean and dedupe
        $images = array_unique(array_filter($images, function($img) {
            // Filter out tiny icons, tracking pixels, etc.
            if (preg_match('/\.(ico|gif)$/i', $img)) return false;
            if (preg_match('/(icon|logo|avatar|pixel|tracking|badge|button)/i', $img)) return false;
            if (preg_match('/[0-9]+x[0-9]+/', $img) && preg_match('/([0-9]+)x/', $img, $m) && (int)$m[1] < 100) return false;
            return true;
        }));

        // Convert relative URLs to absolute
        $parsedBase = parse_url($baseUrl);
        $baseHost = ($parsedBase['scheme'] ?? 'https') . '://' . ($parsedBase['host'] ?? '');

        $images = array_map(function($img) use ($baseHost, $baseUrl) {
            if (str_starts_with($img, '//')) {
                return 'https:' . $img;
            }
            if (str_starts_with($img, '/')) {
                return $baseHost . $img;
            }
            if (!str_starts_with($img, 'http')) {
                return rtrim(dirname($baseUrl), '/') . '/' . $img;
            }
            return $img;
        }, $images);

        return array_values(array_slice($images, 0, 10)); // Limit to 10 images
    }

    private function fetchPage(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ])
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning('EventScraper: Failed to fetch page', [
                'url' => $url,
                'status' => $response->status()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('EventScraper: Exception fetching page', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function cleanHtml(string $html): string
    {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Remove HTML comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Convert to text but preserve some structure
        $html = preg_replace('/<(h[1-6]|p|div|li|br|tr)[^>]*>/i', "\n", $html);
        $html = strip_tags($html);

        // Clean up whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/\n\s*\n/', "\n", $html);

        // Truncate to ~15k chars to fit in context window
        if (strlen($html) > 15000) {
            $html = substr($html, 0, 15000) . "\n[Content truncated...]";
        }

        return trim($html);
    }

    private function extractWithAI(string $content, string $url, array $images = []): array
    {
        $prompt = $this->buildExtractionPrompt($content, $url, $images);

        if ($this->provider === 'anthropic') {
            return $this->extractWithClaude($prompt);
        }

        return $this->extractWithOpenAI($prompt);
    }

    private function extractWithClaude(string $prompt): array
    {
        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'x-api-key' => $this->anthropicApiKey,
                    'Content-Type' => 'application/json',
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-sonnet-4-20250514',
                    'max_tokens' => 4096,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'system' => 'You are an expert at extracting structured event information from web pages. Always respond with valid JSON only, no markdown formatting or code blocks. Just raw JSON.',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $jsonContent = $data['content'][0]['text'] ?? '{}';

                // Strip any markdown code blocks if present
                $jsonContent = preg_replace('/^```json\s*/', '', $jsonContent);
                $jsonContent = preg_replace('/```\s*$/', '', $jsonContent);

                return json_decode($jsonContent, true) ?? [];
            }

            Log::error('EventScraper: Claude API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('EventScraper: Exception calling Claude', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function extractWithOpenAI(string $prompt): array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert at extracting structured event information from web pages. Always respond with valid JSON only, no markdown formatting.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object']
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $jsonContent = $data['choices'][0]['message']['content'] ?? '{}';
                return json_decode($jsonContent, true) ?? [];
            }

            Log::error('EventScraper: OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('EventScraper: Exception calling OpenAI', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function buildExtractionPrompt(string $content, string $url, array $images = []): string
    {
        $imageList = !empty($images)
            ? "IMAGES FOUND ON PAGE:\n" . implode("\n", $images) . "\n\n"
            : "IMAGES: None found\n\n";

        return <<<PROMPT
Extract event information from this web page content. The page is from: {$url}

{$imageList}PAGE CONTENT:
{$content}

---

Extract the following information and return as JSON. For each field, also provide a confidence level ("high", "medium", or "low"). If you cannot find information, use null.

IMPORTANT CATEGORY INSTRUCTIONS:
Choose the BEST matching category from this exact list (use the slug value):
- "immersive-theatre" = Immersive Theatre (live theatrical performances with audience participation)
- "escape-rooms-games" = Escape Rooms & Games
- "telephone" = Telephone (phone-based performances, audio calls with performers)
- "immersive-audio-podplays" = Immersive Audio & Podplays (podcasts, audio dramas)
- "interactive-livestream" = Interactive Livestream (live video streaming events)
- "virtual-reality" = Virtual Reality (VR experiences)
- "augmented-reality" = Augmented Reality (AR experiences)
- "themed-entertainment" = Themed Entertainment (theme parks, themed bars/restaurants)
- "dining-nightlife" = Dining & Nightlife (immersive dining, themed bars)
- "experiential-museum" = Experiential Museum (interactive museum exhibits)
- "festival" = Festival (multi-event festivals)
- "live-action-role-playing-larp" = Live Action Role-Playing (LARP)
- "installation-art" = Installation Art
- "classes-workshops" = Classes & Workshops
- "alternate-reality-gameexperience" = Alternate Reality Game/Experience (ARG)
- "interactive-stories-games" = Interactive Stories & Games (online interactive fiction)
- "virtual-worlds" = Virtual Worlds (online virtual spaces)
- "theatrical-games" = Theatrical Games
- "physical-in-a-box-experiences" = Physical (In-a-Box) Experiences (mailed experiences)
- "magic" = Magic (magic shows)
- "performance-art" = Performance Art
- "immersive-cinema" = Immersive Cinema

For a phone-based one-on-one performance, use "telephone".
For an online experience that isn't phone-based, use "interactive-livestream" or "interactive-stories-games".

Return this exact JSON structure:
{
    "name": "Event title",
    "name_confidence": "high|medium|low",

    "tagline": "Short tagline or subtitle if present",
    "tagline_confidence": "high|medium|low",

    "description": "Event description (up to 5000 chars)",
    "description_confidence": "high|medium|low",

    "category": "Use exact slug from the category list above",
    "category_confidence": "high|medium|low",

    "tags": ["array", "of", "relevant", "tags - use lowercase, try to match common tags like: one-on-one, interactive, immersive, mystery, horror, comedy, family-friendly, 18+, phone, audio, VR, AR, escape room, puzzle, etc."],
    "tags_confidence": "high|medium|low",

    "location_type": "physical|online|hybrid",
    "venue_name": "Venue name if mentioned",
    "street_address": "Full street address if visible",
    "city": "City name",
    "state": "State/province",
    "zip_code": "Postal code",
    "country": "Country",
    "is_secret_location": false,
    "location_confidence": "high|medium|low",

    "date_type": "specific|ongoing|always - use 'specific' if there are particular dates, 'ongoing' if it runs regularly (e.g. every weekend), 'always' if available on-demand",
    "start_date": "YYYY-MM-DD format - first performance date",
    "end_date": "YYYY-MM-DD format - last performance date",
    "specific_dates": ["YYYY-MM-DD", "YYYY-MM-DD"] - array of all specific performance dates if listed (e.g. from a calendar or date picker)",
    "recurring_days": ["friday", "saturday"] - days of week if it's an ongoing run",
    "show_times": "Raw text about show times (e.g. '7pm and 9pm' or 'various times')",
    "dates_notes": "Any complexity about the schedule (dark days, special performances, festival info, etc.)",
    "dates_confidence": "high|medium|low",

    "price_min": 29.99,
    "price_max": 89.99,
    "currency": "USD",
    "price_notes": "Notes about pricing (e.g., 'VIP excluded', 'plus fees')",
    "price_confidence": "high|medium|low",

    "ticket_url": "Direct booking/ticket URL if different from main page",

    "image_urls": ["array", "of", "image", "urls"],
    "primary_image_url": "Best/main image URL",

    "contact_level": "none|low|medium|high - physical contact with actors/participants",
    "contact_level_confidence": "high|medium|low",

    "minimum_age": 18,
    "minimum_age_confidence": "high|medium|low",

    "interaction_level": "observe|light|moderate|heavy - how much audience participates",
    "interaction_level_confidence": "high|medium|low",

    "audience_role": "Description of what role audience plays",
    "audience_role_confidence": "high|medium|low",

    "has_sexual_content": false,
    "content_advisories": ["array", "of", "content", "warnings"],
    "content_confidence": "high|medium|low",

    "wheelchair_accessible": true,
    "mobility_advisories": ["array", "of", "mobility", "notes"],
    "accessibility_confidence": "high|medium|low",

    "organizer_name": "Name of the producing company/organizer",

    "additional_urls": {
        "website": "main website if different",
        "instagram": "instagram url",
        "facebook": "facebook url",
        "twitter": "twitter url"
    },

    "raw_notes": ["Any other useful information found", "That doesn't fit above categories"]
}

IMPORTANT:
- For prices, try to find the base adult ticket price WITHOUT VIP upgrades, add-ons, or premium experiences
- For dates, be specific about what you found vs. inferred
- If the location seems intentionally hidden (like "location revealed after booking"), set is_secret_location to true
- For images: Pick the best event/promotional images from the IMAGES list above. Prefer large hero images, event photos, or promotional artwork. Exclude icons, logos, avatars, and UI elements. The primary_image_url should be the single best image for representing this event (ideally vertical/portrait orientation if available).
- Be honest about confidence levels - use "low" if you're guessing
PROMPT;
    }

    private function buildScrapedEventData(array $data, string $url): ScrapedEventData
    {
        return new ScrapedEventData(
            name: $data['name'] ?? null,
            nameConfidence: $data['name_confidence'] ?? null,

            tagline: $data['tagline'] ?? null,
            taglineConfidence: $data['tagline_confidence'] ?? null,

            description: $data['description'] ?? null,
            descriptionConfidence: $data['description_confidence'] ?? null,

            category: $data['category'] ?? null,
            categoryConfidence: $data['category_confidence'] ?? null,

            tags: $data['tags'] ?? [],
            tagsConfidence: $data['tags_confidence'] ?? null,

            locationType: $data['location_type'] ?? null,
            venueName: $data['venue_name'] ?? null,
            streetAddress: $data['street_address'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            country: $data['country'] ?? null,
            locationConfidence: $data['location_confidence'] ?? null,
            isSecretLocation: $data['is_secret_location'] ?? false,

            dateType: $data['date_type'] ?? null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            specificDates: $data['specific_dates'] ?? [],
            recurringDays: $data['recurring_days'] ?? [],
            showTimes: $data['show_times'] ?? null,
            datesConfidence: $data['dates_confidence'] ?? null,
            datesNotes: $data['dates_notes'] ?? null,

            priceMin: isset($data['price_min']) ? (float) $data['price_min'] : null,
            priceMax: isset($data['price_max']) ? (float) $data['price_max'] : null,
            currency: $data['currency'] ?? 'USD',
            ticketUrl: $data['ticket_url'] ?? null,
            priceConfidence: $data['price_confidence'] ?? null,
            priceNotes: $data['price_notes'] ?? null,

            imageUrls: $data['image_urls'] ?? [],
            primaryImageUrl: $data['primary_image_url'] ?? null,

            contactLevel: $data['contact_level'] ?? null,
            contactLevelConfidence: $data['contact_level_confidence'] ?? null,

            minimumAge: isset($data['minimum_age']) ? (int) $data['minimum_age'] : null,
            minimumAgeConfidence: $data['minimum_age_confidence'] ?? null,

            interactionLevel: $data['interaction_level'] ?? null,
            interactionLevelConfidence: $data['interaction_level_confidence'] ?? null,

            audienceRole: $data['audience_role'] ?? null,
            audienceRoleConfidence: $data['audience_role_confidence'] ?? null,

            hasSexualContent: $data['has_sexual_content'] ?? null,
            contentAdvisories: $data['content_advisories'] ?? [],
            contentConfidence: $data['content_confidence'] ?? null,

            wheelchairAccessible: $data['wheelchair_accessible'] ?? null,
            mobilityAdvisories: $data['mobility_advisories'] ?? [],
            accessibilityConfidence: $data['accessibility_confidence'] ?? null,

            sourceUrl: $url,
            additionalUrls: $data['additional_urls'] ?? [],
            organizerName: $data['organizer_name'] ?? null,
            rawNotes: $data['raw_notes'] ?? [],
            scrapedAt: now()->toIso8601String(),
            scraperUsed: 'generic_ai',
        );
    }
}
