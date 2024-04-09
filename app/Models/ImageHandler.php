<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Encoders\JpegEncoder;



class ImageHandler extends Model
{
    use HasFactory;

    public static function saveImage($request, $value, $width, $height, $type)
    {
        // Validation for the image upload
        if (!$request->hasFile('image') || !$request->file('image')->isValid()) {
            throw new \Exception('Image upload failed or no image provided.');
        }

        // Ensure the uploaded file is an image
        $mimeType = $request->file('image')->getMimeType();
        if (strpos($mimeType, 'image/') !== 0) {
            throw new \Exception('The file is not an image.');
        }

        $name = $value->name ?: substr(md5(microtime()), rand(0, 26), 7);
        $rand = substr(md5(microtime()), rand(0, 26), 7);
        $title = Str::slug($name);
        $directory = "$type-images/$title-$rand"; // Ensures uniqueness

        $imagePath = $request->file('image')->getPathName();
        $image = Image::read($imagePath);

        // Combine title and extension to form 'new-titles.jpg'
        $fileName = $title;

        // Encode to JPG and save
        $jpg = clone $image;
        $jpg->cover($width, $height);
        $encodedJpg = $jpg->encode(new JpegEncoder(quality: 75));
        Storage::disk('digitalocean')->put("/public/$directory/$fileName.jpg", (string) $encodedJpg);

        // Encode to WEBP and save
        $webp = clone $image;
        $webp->cover($width, $height);
        $encodedWebp = $webp->encode(new WebpEncoder(quality: 75));
        Storage::disk('digitalocean')->put("/public/$directory/$fileName.webp", (string) $encodedWebp);

        // Creating thumbnails and encoding to JPG
        $thumbJpg = clone $image;
        $thumbJpg->cover($width / 2, $height / 2);
        $encodedThumbJpg = $thumbJpg->encode(new JpegEncoder(quality: 75));
        Storage::disk('digitalocean')->put("/public/$directory/$fileName-thumb.jpg", (string) $encodedThumbJpg);

        // Encoding thumbnails to WEBP
        $thumbWebp = clone $image;
        $thumbWebp->cover($width / 2, $height / 2);
        $encodedThumbWebp = $thumbWebp->encode(new WebpEncoder(quality: 75));
        Storage::disk('digitalocean')->put("/public/$directory/$fileName-thumb.webp", (string) $encodedThumbWebp);

        // Updating the model with the path to the saved images
        $value->update([
            'largeImagePath' => $directory . '/' . $fileName . '.webp',
            'thumbImagePath' => $directory . '/' . $fileName . '-thumb.webp',
        ]);
    }

}
