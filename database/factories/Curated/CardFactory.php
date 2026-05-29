<?php

namespace Database\Factories\Curated;

use App\Models\Curated\Card;
use App\Models\Curated\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    protected $model = Card::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'event_id' => null,
            'name' => $this->faker->words(3, true),
            'blurb' => $this->faker->sentence(),
            'url' => $this->faker->url(),
            'button_text' => $this->faker->words(2, true),
            'type' => 'b',
            'order' => 0,
        ];
    }
}
