<?php

use App\Models\Event;
use Tests\Support\FakeSearchEngine;

/**
 * The first page of results is read by two islands, the nav's SearchStore
 * and the results list. It is serialized once into window.Laravel.page and
 * each island binds pageDataCopy('searchedEvents') (resources/js/bladeBridge.js);
 * it used to be inlined as an attribute on both, doubling the page.
 */
dataset('search pages', [
    'location, desktop' => ['?city=Los%20Angeles,%20CA&lat=34.0522&lng=-118.2437', 'Mozilla/5.0 (Macintosh)'],
    'location, mobile' => ['?city=Los%20Angeles,%20CA&lat=34.0522&lng=-118.2437', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'],
    'all, desktop' => ['', 'Mozilla/5.0 (Macintosh)'],
    'all, mobile' => ['', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'],
]);

test('the search page embeds its results once, and both islands read that copy', function (string $query, string $userAgent) {
    $event = Event::factory()->published()->create(['name' => 'Embedded Once Show']);
    FakeSearchEngine::install([$event->id]);

    $html = $this->withHeader('User-Agent', $userAgent)->get('/index/search'.$query)->assertOk()->getContent();

    expect(substr_count($html, 'Embedded Once Show'))->toBe(1)
        ->and($html)->toContain('window.Laravel.page = { searchedEvents: JSON.parse(')
        ->and($html)->not->toContain("searched-events='")
        ->and(substr_count($html, ":searched-events=\"pageDataCopy('searchedEvents')\""))->toBe(2);
})->with('search pages');
