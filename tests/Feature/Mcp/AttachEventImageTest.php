<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\AttachEventImage;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ImageIngestException;
use App\Services\RemoteImageIngest;
use Illuminate\Support\Facades\Http;

function imageToolUser(): User
{
    return User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
}

function imageToolEvent(User $user): Event
{
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $user->id, 'status' => '0']);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    return $event;
}

/** A minimal real PNG (1x1) so finfo detects image/png. */
function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
}

/**
 * The real validator resolves DNS, which would reject the fake test hosts
 * before Http::fake() can answer. Skip it for download-behavior tests; the
 * SSRF tests below still exercise the real SafeUrlValidator path.
 */
function skipUrlValidation(): void
{
    app()->bind(\App\Services\RemoteImageIngest::class, fn () => new \App\Services\RemoteImageIngest(fn () => true));
}

// ── SSRF / download validation (service level) ─────────────────────────

test('remote image ingest rejects localhost and private addresses', function (string $url) {
    expect(fn () => app(RemoteImageIngest::class)->fetch($url, 5 * 1024 * 1024))
        ->toThrow(ImageIngestException::class);
})->with([
    'http://localhost/x.png',
    'http://127.0.0.1/x.png',
    'http://169.254.169.254/latest/meta-data.png',
    'http://10.0.0.5/internal.png',
    'http://192.168.1.1/router.png',
    'ftp://example.com/x.png',
]);

test('remote image ingest rejects non-image bytes', function () {
    skipUrlValidation();
    Http::fake(['images.example.com/*' => Http::response('<html>not an image</html>', 200)]);

    expect(fn () => app(RemoteImageIngest::class)->fetch('https://images.example.com/fake.png', 5 * 1024 * 1024))
        ->toThrow(ImageIngestException::class, 'did not return a supported image');
});

test('remote image ingest rejects oversized files', function () {
    skipUrlValidation();
    Http::fake(['images.example.com/*' => Http::response(str_repeat('a', 600), 200)]);

    expect(fn () => app(RemoteImageIngest::class)->fetch('https://images.example.com/big.png', 500))
        ->toThrow(ImageIngestException::class, 'too large');
});

test('remote image ingest rejects http error responses', function () {
    skipUrlValidation();
    Http::fake(['images.example.com/*' => Http::response('', 404)]);

    expect(fn () => app(RemoteImageIngest::class)->fetch('https://images.example.com/missing.png', 5 * 1024 * 1024))
        ->toThrow(ImageIngestException::class, 'HTTP 404');
});

test('remote image ingest accepts a real png and returns an UploadedFile', function () {
    skipUrlValidation();
    Http::fake(['images.example.com/*' => Http::response(tinyPng(), 200)]);

    $file = app(RemoteImageIngest::class)->fetch('https://images.example.com/pixel.png', 5 * 1024 * 1024);

    expect($file->getMimeType())->toBe('image/png');
    expect(file_get_contents($file->getRealPath()))->toBe(tinyPng());
    @unlink($file->getRealPath());
});

// ── tool level ─────────────────────────────────────────────────────────

test('attach-event-image rejects unsafe urls with a readable error', function () {
    $user = imageToolUser();
    $event = imageToolEvent($user);

    $response = EiServer::actingAs($user)->tool(AttachEventImage::class, [
        'event_slug' => $event->slug,
        'image_url' => 'http://127.0.0.1/x.png',
        'rank' => 0,
    ]);

    $response->assertHasErrors();
    expect($event->images()->count())->toBe(0);
});

test('attach-event-image denies non-members', function () {
    $owner = imageToolUser();
    $event = imageToolEvent($owner);
    $stranger = imageToolUser();

    $response = EiServer::actingAs($stranger)->tool(AttachEventImage::class, [
        'event_slug' => $event->slug,
        'image_url' => 'https://images.example.com/pixel.png',
        'rank' => 0,
    ]);

    $response->assertHasErrors();
});

test('attach-event-image enforces the 5 image cap for new ranks', function () {
    $user = imageToolUser();
    $event = imageToolEvent($user);
    foreach (range(0, 4) as $rank) {
        $event->images()->create([
            'large_image_path' => "event-images/{$event->slug}/img-{$rank}.webp",
            'thumb_image_path' => "event-images/{$event->slug}/img-{$rank}-thumb.webp",
            'rank' => $rank,
        ]);
    }

    // With ranks 0-4 all occupied, a same-rank request replaces instead of
    // adding — the count must stay capped at 5.
    skipUrlValidation();
    Http::fake(['images.example.com/*' => Http::response(tinyPng(), 200)]);
    \Illuminate\Support\Facades\Storage::fake('digitalocean');

    $response = EiServer::actingAs($user)->tool(AttachEventImage::class, [
        'event_slug' => $event->slug,
        'image_url' => 'https://images.example.com/pixel.png',
        'rank' => 2,
    ]);

    $response->assertOk();
    expect($event->images()->count())->toBe(5);
});

test('attach-event-image saves through ImageHandler with rank-based dimensions', function () {
    $user = imageToolUser();
    $event = imageToolEvent($user);

    skipUrlValidation();
    Http::fake(['images.example.com/*' => Http::response(tinyPng(), 200)]);
    \Illuminate\Support\Facades\Storage::fake('digitalocean');

    $response = EiServer::actingAs($user)->tool(AttachEventImage::class, [
        'event_slug' => $event->slug,
        'image_url' => 'https://images.example.com/pixel.png',
        'rank' => 0,
    ]);

    $response->assertOk();
    $event->refresh();
    expect($event->images()->where('rank', 0)->count())->toBe(1);
    expect($event->largeImagePath)->not->toBeNull();
});
