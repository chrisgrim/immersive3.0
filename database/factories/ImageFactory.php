<?php

namespace Database\Factories;

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
            // images table columns are imageable_id/imageable_type (polymorphic),
            // large_image_path, thumb_image_path, rank. The morph columns are
            // non-nullable in the schema, so set them via ->for()/state when
            // attaching to a concrete model.
            'imageable_id' => null,
            'imageable_type' => null,
            'large_image_path' => '/storage/images/'.$this->faker->uuid().'.webp',
            'thumb_image_path' => '/storage/images/'.$this->faker->uuid().'-thumb.webp',
            'rank' => 0,
        ];
    }
}
