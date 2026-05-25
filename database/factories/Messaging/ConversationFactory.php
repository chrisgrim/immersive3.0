<?php

namespace Database\Factories\Messaging;

use App\Models\Messaging\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'user_one' => User::factory(),
            'user_two' => User::factory(),
            'subject' => $this->faker->sentence(3),
        ];
    }
}
