<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Support\Validation\EventUpdateRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

// The event photo picker offers AVIF, so the server has to take it (Kathryn,
// 2026-09-30: "The images.0 field must be a file of type: jpeg, png, jpg, webp").
// A real AVIF, not a renamed JPEG, so the mime sniff and the GD decode both run.
function realAvif(): UploadedFile
{
    $im = imagecreatetruecolor(1200, 800);
    imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 200));
    $path = sys_get_temp_dir().'/'.uniqid('avif', true).'.avif';
    imageavif($im, $path);

    return new UploadedFile($path, 'photo.avif', 'image/avif', null, true);
}

test('the event rules accept an AVIF photo', function () {
    $v = Validator::make(['images' => [realAvif()]], ['images.*' => EventUpdateRules::rules()['images.*']]);

    expect($v->errors()->all())->toBe([]);
});

test('an AVIF gallery photo saves through the event update route as webp and jpg', function () {
    Storage::fake('digitalocean');
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'avif-show']);
    $user = User::factory()->create(['type' => 'u']);
    $organizer->users()->attach($user->id, ['role' => 'member']);

    $this->actingAs($user->fresh())
        ->post("/api/hosting/event/{$event->slug}", ['images' => [realAvif()], 'ranks' => [1]],
            ['Accept' => 'application/json'])
        ->assertOk();

    $image = $event->fresh()->images()->where('rank', 1)->firstOrFail();
    $base = preg_replace('/\.webp$/', '', $image->large_image_path);
    Storage::disk('digitalocean')->assertExists("public/{$base}.webp");
    Storage::disk('digitalocean')->assertExists("public/{$base}.jpg");
});
