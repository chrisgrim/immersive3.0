<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    public function definition(): array
    {
        $criteria = [
            'city' => fake()->city(),
            'lat' => null,
            'lng' => null,
            'searchType' => null,
            'live' => false,
            'start' => null,
            'end' => null,
            'categories' => [],
            'tags' => [],
            'price' => null,
        ];

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'criteria' => $criteria,
            'fingerprint' => hash('sha256', json_encode($criteria).fake()->unique()->uuid()),
        ];
    }
}
