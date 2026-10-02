<?php

use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Events\Show;
use App\Models\Organizer;
use App\Support\VueSafe;

/**
 * The layout mounts Vue over the whole body, so Vue compiles every Blade page
 * as a template and runs any {{ }} it finds. User text must never reach the
 * page with a Vue mustache intact (see App\Support\VueSafe).
 */

/** The page as Vue compiles it: the body, minus scripts (Vue skips those). */
function vueCompiledPart(string $html): string
{
    $body = Str::after($html, '<body id="app">');

    return preg_replace('#<script\b.*?</script>#s', '', $body);
}

function injectableEvent(array $overrides): Event
{
    $event = Event::factory()->published()->create(array_merge([
        'organizer_id' => Organizer::factory()->create(['status' => 'p'])->id,
        'closingDate' => now()->addDays(30),
        'hasLocation' => true,
    ], $overrides));
    Location::factory()->create(['event_id' => $event->id]);
    Show::factory()->create(['event_id' => $event->id]);
    $event->advisories()->create(['wheelchairReady' => true]);

    return $event;
}

test('VueSafe breaks up every run of opening braces and nothing else', function () {
    expect(VueSafe::html('{{ x }}'))->toBe("{\u{200B}{ x }}")
        ->and(VueSafe::html('{{{{'))->not->toContain('{{')
        ->and(VueSafe::html('{"a":{"b":1}}'))->toBe('{"a":{"b":1}}')
        ->and(VueSafe::e('<b>{{ x }}</b>'))->toBe("&lt;b&gt;{\u{200B}{ x }}&lt;/b&gt;");
});

test('an event page never hands Vue a mustache from the organizer\'s text', function () {
    $payload = '{{ constructor.constructor("alert(1)")() }}';
    $event = injectableEvent([
        'name' => "Show {$payload}",
        'tag_line' => "Tag {$payload}",
        'call_to_action' => "Book {$payload}",
    ]);

    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();

    expect(vueCompiledPart($html))->not->toContain('{{');
});

test('an event page still emits valid JSON-LD with the original text', function () {
    $event = injectableEvent(['name' => 'Show {{ 7*7 }}']);

    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
    $jsonLd = json_decode($matches[1]);
    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and(str_replace("\u{200B}", '', $jsonLd->name))->toBe('Show {{ 7*7 }}');
});

test('the gallery markup reaches the page as a JS string, so a name cannot break out of a template literal', function () {
    $event = injectableEvent(['name' => 'Show ${alert(1)} `tick`']);
    App\Models\Image::factory()->count(3)->create(['imageable_id' => $event->id, 'imageable_type' => Event::class]);

    foreach (['Mozilla/5.0 (Macintosh)', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'] as $agent) {
        $html = $this->withHeader('User-Agent', $agent)->get("/events/{$event->slug}")->assertOk()->getContent();

        expect($html)->not->toContain('innerHTML = `')
            ->and($html)->toContain("innerHTML = '");
    }
});
