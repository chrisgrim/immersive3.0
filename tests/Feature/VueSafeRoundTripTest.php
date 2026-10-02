<?php

use App\Support\VueSafe;

/**
 * VueSafe breaks "{{" with a zero-width space on the way out (so Vue never
 * runs user text); RestoreVueSafeBraces takes it out again on the way in, so
 * a value an editor read from the page and sends back is saved unchanged.
 */
test('text printed through VueSafe and sent back is restored exactly', function () {
    foreach (['{{ Night }}', '{{{{', 'a{b}', "{\u{200B}x", 'plain'] as $text) {
        expect(VueSafe::restore(VueSafe::html($text)))->toBe($text);
    }
});

test('a live event name containing {{ is not mistaken for a rename when the editor resends it', function () {
    $user = App\Models\User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
    $organizer = App\Models\Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = App\Models\Event::factory()->create([
        'organizer_id' => $organizer->id, 'user_id' => $user->id, 'status' => 'p',
        'name' => '{{ Night }}', 'closingDate' => now()->addMonth(),
    ]);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'name' => VueSafe::html('{{ Night }}'),
        'tag_line' => 'Saved',
    ])->assertOk();

    expect($event->fresh()->name)->toBe('{{ Night }}')->and($event->fresh()->tag_line)->toBe('Saved');
});

test('a request carrying invalid UTF-8 is passed through, not turned into a 500', function () {
    expect(VueSafe::restore("\xFF{{"))->toBe("\xFF{{")
        ->and(VueSafe::html("\xFF{{"))->toBe("\xFF{{");

    $this->get('/up?x=%FF')->assertOk();
});
