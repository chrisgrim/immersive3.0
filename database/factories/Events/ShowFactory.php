<?php

namespace Database\Factories\Events;

use App\Models\Event;
use App\Models\Events\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Show>
 */
class ShowFactory extends Factory
{
    protected $model = Show::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            // The shows table stores a single dateTime column named `date`.
            'date' => $this->faker->dateTimeBetween('now', '+6 months')->format('Y-m-d H:i:s'),
        ];
    }
}
