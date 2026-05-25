<?php

namespace Database\Factories\Messaging;

use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'conversation_id' => Conversation::factory(),
            'message' => '<p>'.$this->faker->sentence().'</p>',
            'is_seen' => 0,
        ];
    }
}
