<?php

namespace Database\Factories\Curated;

use App\Models\Curated\Community;
use App\Models\Curated\Shelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shelf>
 */
class ShelfFactory extends Factory
{
    protected $model = Shelf::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'order' => 0,
            'is_hidden' => false,
            'status' => 'p',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'p']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'd']);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_hidden' => true]);
    }
}
