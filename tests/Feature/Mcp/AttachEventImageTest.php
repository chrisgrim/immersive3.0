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

test('remote image ingest sends a browser User-Agent so hotlink-protected CDNs do not 403', function () {
    skipUrlValidation();
    Http::fake(['images.example.com/*' => Http::response(tinyPng(), 200)]);

    app(RemoteImageIngest::class)->fetch('https://images.example.com/pixel.png', 5 * 1024 * 1024);

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'Mozilla/5.0'));
});

test('remote image ingest rejects bytes that pass finfo but fail decoding', function () {
    skipUrlValidation();
    // A valid PNG signature followed by garbage: finfo says image/png,
    // Intervention cannot decode it.
    $corrupt = substr(tinyPng(), 0, 20).str_repeat('x', 50);
    Http::fake(['images.example.com/*' => Http::response($corrupt, 200)]);

    expect(fn () => app(RemoteImageIngest::class)->fetch('https://images.example.com/corrupt.png', 5 * 1024 * 1024))
        ->toThrow(ImageIngestException::class, 'could not be decoded');
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

/** The JSON-RPC content blocks behind a tool TestResponse. */
function toolContent(\Laravel\Mcp\Server\Testing\TestResponse $response): array
{
    $raw = (fn () => $this->response)->call($response)->toArray();

    return $raw['result']['content'];
}

test('attach-event-image returns the cropped thumbnail as an image block', function () {
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

    $content = toolContent($response);
    $images = array_values(array_filter($content, fn ($block) => ($block['type'] ?? null) === 'image'));

    // The JSON summary still comes back alongside the preview.
    expect(array_filter($content, fn ($block) => ($block['type'] ?? null) === 'text'))->not->toBeEmpty();

    expect($images)->toHaveCount(1);
    expect($images[0]['mimeType'])->toBe('image/jpeg');
    // Real JPEG bytes — not the path string, and not the WebP twin.
    expect(substr(base64_decode($images[0]['data']), 0, 2))->toBe("\xFF\xD8");
});

test('attach-event-image preview reads the jpeg twin of the stored webp thumb', function () {
    $user = imageToolUser();
    $event = imageToolEvent($user);

    skipUrlValidation();
    Http::fake(['images.example.com/*' => Http::response(tinyPng(), 200)]);
    \Illuminate\Support\Facades\Storage::fake('digitalocean');

    EiServer::actingAs($user)->tool(AttachEventImage::class, [
        'event_slug' => $event->slug,
        'image_url' => 'https://images.example.com/pixel.png',
        'rank' => 1,
    ]);

    $stored = $event->images()->where('rank', 1)->first();
    expect($stored->thumb_image_path)->toEndWith('.webp');

    $jpegTwin = '/public/'.preg_replace('/\.webp$/', '.jpg', $stored->thumb_image_path);
    \Illuminate\Support\Facades\Storage::disk('digitalocean')->assertExists($jpegTwin);
});

test('a preview that cannot be read back is dropped, not turned into an error', function () {
    \Illuminate\Support\Facades\Storage::fake('digitalocean');

    // An image row whose files are gone from storage — the read-back throws
    // (or misses) and the tool must simply omit the preview block.
    $orphan = new \App\Models\Image([
        'large_image_path' => 'event-images/gone/gone-1.webp',
        'thumb_image_path' => 'event-images/gone/gone-1-thumb.webp',
        'rank' => 0,
    ]);

    $preview = (fn () => $this->preview($orphan))->call(new AttachEventImage);

    expect($preview)->toBeNull();
});
