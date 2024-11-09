<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Messaging\Conversation;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConversationPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Conversation $conversation)
    {
        return $user->id === $conversation->user_one 
            || $user->id === $conversation->user_two 
            || $user->type === 'a' 
            || $user->type === 'm';
    }
    
    public function update(User $user, Conversation $conversation)
    {
        return $user->id === $conversation->user_one 
            || $user->id === $conversation->user_two 
            || $user->type === 'a' 
            || $user->type === 'm';
    }
}
