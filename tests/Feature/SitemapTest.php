<?php

use App\Models\Curated\Community;
use App\Models\Event;
use App\Models\Organizer;

// SitemapController@index (GET /sitemap.xml) renders an XML sitemap including
// events with status in ['p','e'] that have a non-empty slug, organizers that
// have events, and communities with status 'p'.

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

test('sitemap includes embargoed events', function () {
    Event::factory()->create(['status' => 'e', 'slug' => 'embargoed-event-sitemap']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toContain('embargoed-event-sitemap');
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
