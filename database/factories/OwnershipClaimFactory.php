<?php

namespace Database\Factories;

use App\Models\Organizer;
use App\Models\OwnershipClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnershipClaim>
 */
class OwnershipClaimFactory extends Factory
{
    protected $model = OwnershipClaim::class;

    public function definition(): array
    {
        return [
            'organizer_id' => Organizer::factory(),
            'user_id' => User::factory(),
            'message' => $this->faker->sentence(),
            // status defaults to 'pending' at the DB level; processed_* are set by the service.
        ];
    }
}
