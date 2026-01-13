<?php

namespace App\Services\EventScraper;

/**
 * Data Transfer Object for scraped event information.
 *
 * Each field has a confidence level:
 * - 'high': Extracted with high confidence, likely correct
 * - 'medium': Best guess, should be reviewed
 * - 'low': Uncertain or partially found
 * - null: Not found
 */
class ScrapedEventData
{
    public function __construct(
        // Basic info
        public ?string $name = null,
        public ?string $nameConfidence = null,

        public ?string $tagline = null,
        public ?string $taglineConfidence = null,

        public ?string $description = null,
        public ?string $descriptionConfidence = null,

        // Category & Tags
        public ?string $category = null, // escape_room, immersive_theatre, festival, themed_entertainment
        public ?string $categoryConfidence = null,

        public array $tags = [], // dance, participatory, mystery, game, binaural, etc.
        public ?string $tagsConfidence = null,

        // Location
        public ?string $locationType = null, // physical, online, hybrid
        public ?string $venueName = null,
        public ?string $streetAddress = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $zipCode = null,
        public ?string $country = null,
        public ?string $locationConfidence = null,
        public bool $isSecretLocation = false,

        // Dates
        public ?string $dateType = null, // specific, ongoing, always
        public ?string $startDate = null,
        public ?string $endDate = null,
        public array $specificDates = [], // ['2024-01-15', '2024-01-16', ...] individual show dates
        public array $recurringDays = [], // ['friday', 'saturday', 'sunday']
        public ?string $showTimes = null, // Raw text about show times
        public ?string $datesConfidence = null,
        public ?string $datesNotes = null, // Any complexities found

        // Tickets
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public ?string $currency = 'USD',
        public ?string $ticketUrl = null,
        public ?string $priceConfidence = null,
        public ?string $priceNotes = null, // "VIP excluded", "preview pricing", etc.

        // Images
        public array $imageUrls = [],
        public ?string $primaryImageUrl = null,

        // Experience details
        public ?string $contactLevel = null, // none, low, medium, high
        public ?string $contactLevelConfidence = null,

        public ?int $minimumAge = null,
        public ?string $minimumAgeConfidence = null,

        public ?string $interactionLevel = null, // observe, light, moderate, heavy
        public ?string $interactionLevelConfidence = null,

        public ?string $audienceRole = null,
        public ?string $audienceRoleConfidence = null,

        // Content advisories
        public ?bool $hasSexualContent = null,
        public array $contentAdvisories = [],
        public ?string $contentConfidence = null,

        // Accessibility
        public ?bool $wheelchairAccessible = null,
        public array $mobilityAdvisories = [],
        public ?string $accessibilityConfidence = null,

        // Meta
        public ?string $sourceUrl = null,
        public array $additionalUrls = [], // Other URLs found (social, ticketing, etc.)
        public ?string $organizerName = null,
        public array $rawNotes = [], // Any other useful info the AI found
        public ?string $scrapedAt = null,
        public ?string $scraperUsed = null,
    ) {}

    /**
     * Get all fields that need human review (medium or low confidence)
     */
    public function getFieldsNeedingReview(): array
    {
        $needsReview = [];

        $confidenceFields = [
            'name' => $this->nameConfidence,
            'tagline' => $this->taglineConfidence,
            'description' => $this->descriptionConfidence,
            'category' => $this->categoryConfidence,
            'tags' => $this->tagsConfidence,
            'location' => $this->locationConfidence,
            'dates' => $this->datesConfidence,
            'price' => $this->priceConfidence,
            'contactLevel' => $this->contactLevelConfidence,
            'minimumAge' => $this->minimumAgeConfidence,
            'interactionLevel' => $this->interactionLevelConfidence,
            'audienceRole' => $this->audienceRoleConfidence,
            'content' => $this->contentConfidence,
            'accessibility' => $this->accessibilityConfidence,
        ];

        foreach ($confidenceFields as $field => $confidence) {
            if ($confidence === 'medium' || $confidence === 'low' || $confidence === null) {
                $needsReview[$field] = $confidence ?? 'not_found';
            }
        }

        return $needsReview;
    }

    /**
     * Get completion percentage (how many fields were found)
     */
    public function getCompletionPercentage(): int
    {
        $requiredFields = [
            $this->name,
            $this->description,
            $this->category,
            $this->city ?? $this->locationType,
            $this->dateType,
            $this->priceMin,
        ];

        $filled = count(array_filter($requiredFields, fn($v) => $v !== null));
        return (int) round(($filled / count($requiredFields)) * 100);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
