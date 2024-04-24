<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Messaging\Conversation;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConversationPolicy
{
    use HandlesAuthorization;

    
    public function update(User $user, Conversation $conversation)
    {
        return $conversation->users->contains('id', $user->id) || $user->type == 'a' || $user->type == 'm';
    }

}
