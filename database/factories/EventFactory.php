<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $name = $this->faker->sentence(3);
        return [
            'user_id' => User::factory(),
            'organizer_id' => Organizer::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(8),
            'description' => $this->faker->paragraph(),
            'status' => 'd',
            'showtype' => 's',
            'hasLocation' => true,
            'archived' => false,
            'rank' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'p',
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'd']);
    }

    public function inReview(): static
    {
        return $this->state(fn () => ['status' => 'r']);
    }
}
