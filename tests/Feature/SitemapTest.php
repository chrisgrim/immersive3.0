<?php

use App\Models\Category;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Event;
use App\Models\Organizer;

// SitemapController@index (GET /sitemap.xml) renders an XML sitemap of
// published events (upcoming first, past ones kept at a lower priority),
// published organizers with a published event, published communities, their
// published posts and category pages. Built once an hour (cached).

test('sitemap responds 200 with an xml content type', function () {
    $response = $this->get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/xml');
});

test('sitemap body starts with an xml urlset declaration', function () {
    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain('<?xml version="1.0" encoding="UTF-8"?>');
    expect($body)->toContain('<urlset');
    expect($body)->toContain('</urlset>');
});

test('sitemap includes a published event slug but not a draft event slug', function () {
    $published = Event::factory()->published()->create(['slug' => 'published-event-sitemap']);
    $draft = Event::factory()->draft()->create(['slug' => 'draft-event-sitemap']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain('published-event-sitemap');
    expect($body)->not->toContain('draft-event-sitemap');
});

test('sitemap leaves out embargoed events, so their names stay unannounced', function () {
    Event::factory()->create(['status' => 'e', 'slug' => 'embargoed-event-sitemap']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->not->toContain('embargoed-event-sitemap');
});

test('sitemap lists only published organizers that have a published event', function () {
    $pending = Organizer::factory()->create(['name' => 'Sitemap Org Pending', 'status' => 'r']);
    Event::factory()->published()->create(['organizer_id' => $pending->id]);
    $draftsOnly = Organizer::factory()->create(['name' => 'Sitemap Org Drafts Only']);
    Event::factory()->create(['organizer_id' => $draftsOnly->id, 'status' => 'd']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->not->toContain("/organizers/{$pending->slug}")
        ->and($body)->not->toContain("/organizers/{$draftsOnly->slug}");
});

test('sitemap excludes events that are in review or rejected', function () {
    Event::factory()->inReview()->create(['slug' => 'inreview-event-sitemap']);
    Event::factory()->create(['status' => 'n', 'slug' => 'other-event-sitemap']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->not->toContain('inreview-event-sitemap');
    expect($body)->not->toContain('other-event-sitemap');
});

test('sitemap excludes published events with an empty slug', function () {
    // The query requires whereNotNull('slug') and slug != '', so a blank slug
    // published event is excluded from the sitemap.
    Event::factory()->published()->create(['slug' => '']);
    $kept = Event::factory()->published()->create(['slug' => 'has-a-slug-sitemap']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain('has-a-slug-sitemap');
});

test('sitemap includes organizers that have events but not organizers without events', function () {
    // note: Organizer auto-generates its slug from `name` on creating(), so we
    // read the persisted slug rather than asserting a slug we passed in.
    $withEvents = Organizer::factory()->create(['name' => 'Sitemap Org With Events']);
    Event::factory()->published()->create(['organizer_id' => $withEvents->id]);

    $without = Organizer::factory()->create(['name' => 'Sitemap Org Without Events']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain("/organizers/{$withEvents->slug}");
    // Organizer::has('events') excludes organizers with no events.
    expect($body)->not->toContain("/organizers/{$without->slug}");
});

test('sitemap includes published communities but not draft communities', function () {
    // note: Community auto-generates its slug from `name` on creating().
    $published = Community::factory()->create(['status' => 'p', 'name' => 'Sitemap Published Community']);
    $draft = Community::factory()->create(['status' => 'd', 'name' => 'Sitemap Draft Community']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain("communities/{$published->slug}");
    expect($body)->not->toContain("communities/{$draft->slug}");
});

test('sitemap always includes static pages', function () {
    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain(url('/'));
});

test('sitemap lastmod reflects real content changes, not request time', function () {
    $stale = Event::factory()->published()->create(['slug' => 'stale-event-sitemap']);
    $fresh = Event::factory()->published()->create(['slug' => 'fresh-event-sitemap']);
    Event::whereKey($stale->id)->update(['updated_at' => now()->subYear()]);
    Event::whereKey($fresh->id)->update(['updated_at' => now()->subDays(3)]);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    // The home entry's lastmod is the latest event update — not request time
    preg_match('#<url>\s*<loc>'.preg_quote(url('/'), '#').'</loc>\s*<lastmod>([^<]+)</lastmod>#', $body, $home);
    expect($home[1])->toBe(now()->subDays(3)->toIso8601String())
        ->and($body)->toContain(now()->subYear()->toIso8601String());
});

function sitemapPriorityOf(string $body, string $path): ?string
{
    preg_match('#<loc>[^<]*'.preg_quote($path, '#').'</loc>.*?<priority>([^<]+)</priority>#s', $body, $m);

    return $m[1] ?? null;
}

test('sitemap keeps past events, below upcoming ones', function () {
    Event::factory()->published()->create(['slug' => 'upcoming-sitemap', 'closingDate' => now()->addMonth()]);
    Event::factory()->published()->create(['slug' => 'past-sitemap', 'closingDate' => now()->subYears(2)]);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect(sitemapPriorityOf($body, '/events/upcoming-sitemap'))->toBe('0.8')
        ->and(sitemapPriorityOf($body, '/events/past-sitemap'))->toBe('0.3');
});

test('sitemap lists published posts of published communities, and category pages', function () {
    $community = Community::factory()->create(['status' => 'p']);
    $live = Post::factory()->create(['community_id' => $community->id, 'status' => 'p', 'is_hidden' => false]);
    $draft = Post::factory()->create(['community_id' => $community->id, 'status' => 'd']);
    $hidden = Post::factory()->create(['community_id' => $community->id, 'status' => 'p', 'is_hidden' => true]);
    $pending = Community::factory()->create(['status' => 'r']);
    $pendingPost = Post::factory()->create(['community_id' => $pending->id, 'status' => 'p', 'is_hidden' => false]);
    $category = Category::factory()->create();
    Event::factory()->published()->create(['category_id' => $category->id]);
    $emptyCategory = Category::factory()->create();

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain("/communities/{$community->slug}/posts/{$live->slug}")
        ->and($body)->not->toContain("/posts/{$draft->slug}<")
        ->and($body)->not->toContain("/posts/{$hidden->slug}<")
        ->and($body)->not->toContain("/posts/{$pendingPost->slug}<")
        ->and($body)->toContain("/index/search?category={$category->id}&amp;searchType=allEvents")
        ->and($body)->not->toContain("category={$emptyCategory->id}&amp;");
});

test('sitemap is built once and then served from the cache', function () {
    $this->get('/sitemap.xml')->assertOk();
    Event::factory()->published()->create(['slug' => 'added-after-build-sitemap']);

    expect($this->get('/sitemap.xml')->getContent())->not->toContain('added-after-build-sitemap');

    Cache::forget('sitemap.xml');
    expect($this->get('/sitemap.xml')->getContent())->toContain('added-after-build-sitemap');
});
