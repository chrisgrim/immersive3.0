<?php

use App\Services\EventScraper\ScrapedEventData;

// note: This is a pure Unit test for the ScrapedEventData DTO — no database is
// touched, so it intentionally lives outside the Feature suite (which auto-gets
// RefreshDatabase via Pest.php) and does NOT use RefreshDatabase.

// ----- getFieldsNeedingReview() -----

test('getFieldsNeedingReview returns medium, low and null (not_found) confidence fields', function () {
    $data = new ScrapedEventData(
        name: 'High thing',
        nameConfidence: 'high',
        description: 'Medium thing',
        descriptionConfidence: 'medium',
        category: 'Low thing',
        categoryConfidence: 'low',
        // every other confidence field is left null
    );

    $review = $data->getFieldsNeedingReview();

    // High-confidence field is excluded entirely.
    expect($review)->not->toHaveKey('name');

    // medium/low keep their literal confidence string as the value.
    expect($review)->toHaveKey('description')
        ->and($review['description'])->toBe('medium');
    expect($review)->toHaveKey('category')
        ->and($review['category'])->toBe('low');

    // null confidence is reported as the sentinel string 'not_found'.
    expect($review)->toHaveKey('tagline')
        ->and($review['tagline'])->toBe('not_found');
});

test('getFieldsNeedingReview excludes every field when all confidences are high', function () {
    // note: the method only inspects the 14 mapped *Confidence properties; it
    // uses logical field names (e.g. "location", "dates", "price") rather than
    // the raw property names.
    $data = new ScrapedEventData(
        nameConfidence: 'high',
        taglineConfidence: 'high',
        descriptionConfidence: 'high',
        categoryConfidence: 'high',
        tagsConfidence: 'high',
        locationConfidence: 'high',
        datesConfidence: 'high',
        priceConfidence: 'high',
        contactLevelConfidence: 'high',
        minimumAgeConfidence: 'high',
        interactionLevelConfidence: 'high',
        audienceRoleConfidence: 'high',
        contentConfidence: 'high',
        accessibilityConfidence: 'high',
    );

    expect($data->getFieldsNeedingReview())->toBe([]);
});

test('getFieldsNeedingReview maps confidence properties to logical field names', function () {
    $data = new ScrapedEventData(
        locationConfidence: 'medium',
        datesConfidence: 'low',
        priceConfidence: 'medium',
        // leave the rest null
    );

    $review = $data->getFieldsNeedingReview();

    expect($review['location'])->toBe('medium');
    expect($review['dates'])->toBe('low');
    expect($review['price'])->toBe('medium');
    // The exact field-name vocabulary the UI keys off of.
    expect(array_keys($review))->toBe([
        'name', 'tagline', 'description', 'category', 'tags', 'location',
        'dates', 'price', 'contactLevel', 'minimumAge', 'interactionLevel',
        'audienceRole', 'content', 'accessibility',
    ]);
});

test('getFieldsNeedingReview returns all 14 fields when nothing was found', function () {
    $review = (new ScrapedEventData)->getFieldsNeedingReview();

    expect($review)->toHaveCount(14);
    expect(array_values(array_unique($review)))->toBe(['not_found']);
});

// ----- getCompletionPercentage() -----

test('getCompletionPercentage returns 50 when 3 of 6 required fields are present', function () {
    $data = new ScrapedEventData(
        name: 'My Event',
        description: 'A description',
        category: 'festival',
        // city/locationType, dateType, priceMin all null => 3 of 6
    );

    expect($data->getCompletionPercentage())->toBe(50);
});

test('getCompletionPercentage returns 0 when nothing is found', function () {
    expect((new ScrapedEventData)->getCompletionPercentage())->toBe(0);
});

test('getCompletionPercentage returns 100 when all six required fields are present', function () {
    $data = new ScrapedEventData(
        name: 'My Event',
        description: 'A description',
        category: 'festival',
        city: 'London',
        dateType: 'specific',
        priceMin: 25.0,
    );

    expect($data->getCompletionPercentage())->toBe(100);
});

test('getCompletionPercentage rounds 1 of 6 to 17 percent', function () {
    // 1/6 = 16.66… which round() takes to 17.
    expect((new ScrapedEventData(name: 'Only a name'))->getCompletionPercentage())->toBe(17);
});

test('getCompletionPercentage rounds 5 of 6 to 83 percent', function () {
    $data = new ScrapedEventData(
        name: 'My Event',
        description: 'A description',
        category: 'festival',
        city: 'London',
        dateType: 'specific',
        // priceMin null => 5 of 6 = 83.33 -> 83
    );

    expect($data->getCompletionPercentage())->toBe(83);
});

test('getCompletionPercentage falls back to locationType when city is missing', function () {
    // note: the location slot is `$this->city ?? $this->locationType`, so an
    // online-only event with no city still counts that required field.
    $data = new ScrapedEventData(locationType: 'online');

    // Only the location slot is filled => 1 of 6 = 17.
    expect($data->getCompletionPercentage())->toBe(17);
});

test('getCompletionPercentage counts a priceMin of 0.0 as present', function () {
    // note: the filter is fn($v) => $v !== null, so a genuinely free event
    // (priceMin 0.0) still counts toward completion — only null is "missing".
    $data = new ScrapedEventData(priceMin: 0.0);

    expect($data->getCompletionPercentage())->toBe(17);
});

// ----- toArray() -----

test('toArray exposes every public property including the confidence fields', function () {
    $arr = (new ScrapedEventData(name: 'X', nameConfidence: 'high'))->toArray();

    expect($arr)->toHaveKey('name')->and($arr['name'])->toBe('X');
    expect($arr)->toHaveKey('nameConfidence')->and($arr['nameConfidence'])->toBe('high');
    expect($arr)->toHaveKey('additionalUrls')->and($arr['additionalUrls'])->toBe([]);
    expect($arr['currency'])->toBe('USD');
});
