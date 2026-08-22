<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserLocation>
 */
class UserLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'region' => fake()->stateAbbr(),
            'country' => 'USA',
            'postal_code' => fake()->postcode(),
        ];
    }
}
