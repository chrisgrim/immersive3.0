<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;

class ImageHandler extends Model
{
    use HasFactory;

    public static function saveImage($request, $value, $width, $height, $type)
    {
        // create either new-titles or random like 69sjj3s
        $name = $value->name ? $value->name : substr(md5(microtime()),rand(0,26),7);

        // generate rand variable like 546ds3g
        $rand = substr(md5(microtime()),rand(0,26),7);

        // create title like: new-titles
        $title = Str::slug($name);

        // get extension: (jpg)
        $extension = $request->file('image')->getClientOriginalExtension();

        // combine title and extension:  new-titles.jpg
        $inputFile= $title . '.' . $extension;

        // create filename and set it = to title: new-titles
        $fileName= $title;

        // create directory: event-images/new-titles-54fwd3g
        $directory= $type . '-images/' . $title . '-' . $rand;

        $jpg = Image::make($request->file('image'))->orientate()->fit( $width, $height )->encode('jpg');
        $webp = Image::make($jpg)->encode('webp');
        Storage::disk('digitalocean')->put( "/public/$directory/$fileName.jpg", $jpg);
        Storage::disk('digitalocean')->put( "/public/$directory/$fileName.webp", $webp);

        $jpg = Image::make($jpg)->fit( $width / 2, $height / 2 );
        $webp = Image::make($jpg)->encode('webp');
        Storage::disk('digitalocean')->put( "/public/$directory/$fileName-thumb.jpg", $jpg);
        Storage::disk('digitalocean')->put( "/public/$directory/$fileName-thumb.webp", $webp);

        $value->update([ 
            'largeImagePath' => $directory . '/' . $fileName. '.webp',
            'thumbImagePath' => $directory . '/' . $fileName. '-thumb.webp',
        ]);
    }
}
