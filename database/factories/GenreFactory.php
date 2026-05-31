<?php

namespace Database\Factories;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Genre>
 */
class GenreFactory extends Factory
{
    protected $model = Genre::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            // The genres table requires a user_id (non-nullable FK column),
            // so default to a created user; override with null/an id as needed.
            'user_id' => User::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::random(8),
            'admin' => false,
            'rank' => 0,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['admin' => true]);
    }
}
