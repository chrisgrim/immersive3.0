<?php

namespace Database\Factories\Events;

use App\Models\Event;
use App\Models\Events\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'venue' => $this->faker->company(),
            'street' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'region' => $this->faker->stateAbbr(),
            'country' => $this->faker->country(),
            'postal_code' => $this->faker->postcode(),
            'longitude' => $this->faker->longitude(),
            'latitude' => $this->faker->latitude(),
            'hiddenLocationToggle' => false,
        ];
    }
}
