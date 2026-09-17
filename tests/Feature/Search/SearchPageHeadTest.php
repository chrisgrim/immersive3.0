<?php

use App\Models\Category;
use Tests\Support\FakeSearchEngine;

/**
 * The search page ships a <title> and a canonical for every filter
 * combination (resources/views/search/meta.blade.php). The category pages
 * are the only search URLs robots.txt lets Google crawl, and before this
 * they had neither, so Google chose a canonical for them itself and changed
 * its mind between crawls (Search Console "Duplicate without user-selected
 * canonical" on /index/search?category=29&searchType=allEvents, 2026-09-16).
 */
function searchPageHead(string $query, ?string $userAgent = null): string
{
    FakeSearchEngine::install([]);

    $request = $userAgent ? test()->withHeader('User-Agent', $userAgent) : test();

    return $request->get('/index/search'.$query)->assertOk()->getContent();
}

function canonicalOf(string $html): ?string
{
    preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $m);

    // Blade escapes the & between query parameters to &amp;, which is the
    // correct HTML and what every consumer decodes back.
    return isset($m[1]) ? html_entity_decode($m[1]) : null;
}

function titleOf(string $html): ?string
{
    preg_match('#<title>(.*?)</title>#s', $html, $m);

    return isset($m[1]) ? trim($m[1]) : null;
}

test('a single-category search canonicalises to the category URL the site links to', function () {
    $category = Category::factory()->create(['name' => 'Immersive Theatre', 'slug' => 'immersive-theatre']);

    $html = searchPageHead("?category={$category->id}&searchType=allEvents");

    expect(canonicalOf($html))->toBe(url("/index/search?category={$category->id}&searchType=allEvents"));
    expect(titleOf($html))->toBe('Immersive Theatre - Everything Immersive');
});

test('the category canonical ignores how the category page was reached', function () {
    $category = Category::factory()->create(['name' => 'Immersive Theatre', 'slug' => 'immersive-theatre']);
    $expected = url("/index/search?category={$category->id}&searchType=allEvents");

    // Slug instead of id, a different tab, a Show-more depth, another filter:
    // all views of the one category page.
    foreach ([
        "?category={$category->slug}",
        "?searchType=inPerson&category={$category->id}",
        "?category={$category->id}&searchType=allEvents&page=3",
        "?category={$category->id}&searchType=allEvents&price0=0&price1=50",
    ] as $query) {
        expect(canonicalOf(searchPageHead($query)))->toBe($expected, $query);
    }
});

test('every other search canonicalises to the bare search page', function () {
    $a = Category::factory()->create(['slug' => 'cat-a']);
    $b = Category::factory()->create(['slug' => 'cat-b']);

    foreach ([
        '',
        '?searchType=atHome',
        "?category={$a->id},{$b->id}&searchType=allEvents",
        '?category=999999&searchType=allEvents',
        '?searchType=inPerson&live=true&lat=34.05&lng=-118.24&city=Los+Angeles',
    ] as $query) {
        $html = searchPageHead($query);

        expect(canonicalOf($html))->toBe(url('/index/search'), $query);
        expect(titleOf($html))->toBe('Search Events - Everything Immersive', $query);
    }
});

test('the mobile search page carries the same head tags', function () {
    $category = Category::factory()->create(['name' => 'Immersive Theatre', 'slug' => 'immersive-theatre']);
    $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    $html = searchPageHead("?category={$category->id}&searchType=allEvents", $iphone);

    expect(canonicalOf($html))->toBe(url("/index/search?category={$category->id}&searchType=allEvents"));
    expect(titleOf($html))->toBe('Immersive Theatre - Everything Immersive');
});
