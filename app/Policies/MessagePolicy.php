<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Messaging\Message;
use Illuminate\Auth\Access\HandlesAuthorization;

class MessagePolicy
{
    use HandlesAuthorization;

    public function view(User $user, Message $message)
    {
        return $message->conversation->user_one === $user->id 
            || $message->conversation->user_two === $user->id 
            || $user->type === 'a' 
            || $user->type === 'm';
    }

    public function update(User $user, Message $message)
    {
        return $message->user_id === $user->id 
            || $user->type === 'a' 
            || $user->type === 'm';
    }
}
