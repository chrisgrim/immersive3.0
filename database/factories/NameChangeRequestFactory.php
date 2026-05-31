<?php

namespace Database\Factories;

use App\Models\NameChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NameChangeRequest>
 */
class NameChangeRequestFactory extends Factory
{
    protected $model = NameChangeRequest::class;

    public function definition(): array
    {
        return [
            // Polymorphic target; set via ->for($model, 'requestable') or state.
            'requestable_id' => null,
            'requestable_type' => null,
            'current_name' => $this->faker->company(),
            'requested_name' => $this->faker->company(),
            'status' => 'pending',
            'reason' => $this->faker->sentence(),
            'user_id' => User::factory(),
        ];
    }
}
