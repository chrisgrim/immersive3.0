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

    public static function saveImage($image, $model, $width, $height, $type, $rank = 0)
    {

        $mimeType = $image->getMimeType();
        if (strpos($mimeType, 'image/') !== 0) {
            throw new \Exception('The file is not an image.');
        }

        $name = $model->name ?: substr(md5(microtime()), rand(0, 26), 7);
        $rand = substr(md5(microtime()), rand(0, 26), 7);
        $title = Str::slug($name);
        $directory = "$type-images/$title-$rand";

        $imagePath = $image->getPathName();
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

        $model->images()->create([
            'large_image_path' => "$directory/$fileName.webp",
            'thumb_image_path' => "$directory/{$fileName}-thumb.webp",
        ]);

    }

    public static function deleteImage($image)
    {
        // Delete images from storage
        Storage::disk('digitalocean')->delete([
            "/public/{$image->large_image_path}",
            "/public/{$image->thumb_image_path}"
        ]);

        // Delete the image record from database
        $image->delete();
    }

    public static function updateImages($model, $currentImages)
    {
        if ($currentImages) {
            $existingImages = $model->images->pluck('large_image_path', 'id')->toArray();
            $currentImagePaths = array_column($currentImages, 'url');
            
            $imagesToDelete = array_diff($existingImages, $currentImagePaths);

            foreach ($imagesToDelete as $id => $imagePath) {
                $image = $model->images()->find($id);
                if ($image) {
                    self::deleteImage($image);
                }
            }

            foreach ($currentImages as $index => $currentImage) {
                $image = $model->images()->where('large_image_path', $currentImage['url'])->first();
                if ($image) {
                    $image->rank = $index;
                    $image->save();
                }
            }
        }
    }


}