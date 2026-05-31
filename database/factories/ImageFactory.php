<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Image;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    protected $model = Image::class;

    public function definition(): array
    {
        return [
            // images.imageable_id/imageable_type (polymorphic parent) are NOT NULL in the
            // schema, so a bare create() needs a real parent. Default to an Event; attach to
            // a different model with ->for($model, 'imageable') or by passing imageable_id +
            // imageable_type (Organizer, Community, Category, ...) — those override the default
            // before it is resolved, so no throwaway Event is created.
            'imageable_id' => Event::factory(),
            'imageable_type' => Event::class,
            'large_image_path' => '/storage/images/'.$this->faker->uuid().'.webp',
            'thumb_image_path' => '/storage/images/'.$this->faker->uuid().'-thumb.webp',
            'rank' => 0,
        ];
    }
}
