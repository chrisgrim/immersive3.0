<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\TrackClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackClick>
 */
class TrackClickFactory extends Factory
{
    protected $model = TrackClick::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'organizer_id' => Organizer::factory(),
            'user_id' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'click_type' => 'link',
        ];
    }
}
