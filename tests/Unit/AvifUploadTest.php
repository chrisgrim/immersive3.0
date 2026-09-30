<?php

use App\Support\Validation\EventUpdateRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Tests\TestCase;

// The event photo picker offers AVIF, so the server has to take it (Kathryn,
// 2026-09-30: "The images.0 field must be a file of type: jpeg, png, jpg, webp").
// A real AVIF, not a renamed JPEG, so the mime sniff and the GD decode both run.
// No database: this is the rule and the decode, which is all AVIF changes.
uses(TestCase::class);

function realAvif(): UploadedFile
{
    $im = imagecreatetruecolor(800, 600);
    imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 200));
    $path = tempnam(sys_get_temp_dir(), 'avif').'.avif';
    imageavif($im, $path);

    return new UploadedFile($path, 'photo.avif', 'image/avif', null, true);
}

test('the event rules accept an AVIF photo', function () {
    $v = Validator::make(['images' => [realAvif()]], ['images.*' => EventUpdateRules::rules()['images.*']]);

    expect($v->errors()->all())->toBe([]);
});

test('an AVIF decodes and re-encodes the way ImageHandler::saveImage does it', function () {
    $image = Image::read(realAvif()->getPathName());

    $jpg = (string) (clone $image)->cover(800, 600)->encode(new JpegEncoder(quality: 75));
    $webp = (string) (clone $image)->cover(800, 600)->encode(new WebpEncoder(quality: 75));

    expect(substr($jpg, 0, 3))->toBe("\xFF\xD8\xFF");
    expect(substr($webp, 8, 4))->toBe('WEBP');
});
