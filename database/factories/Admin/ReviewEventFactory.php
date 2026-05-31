<?php

namespace Database\Factories\Admin;

use App\Models\Admin\ReviewEvent;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewEvent>
 */
class ReviewEventFactory extends Factory
{
    protected $model = ReviewEvent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            // organizer_id is a non-nullable FK on review_events.
            'organizer_id' => Organizer::factory(),
            'reviewer_name' => $this->faker->name(),
            'url' => $this->faker->url(),
            'review' => $this->faker->paragraph(),
            'rank' => $this->faker->numberBetween(1, 5),
        ];
    }
}
