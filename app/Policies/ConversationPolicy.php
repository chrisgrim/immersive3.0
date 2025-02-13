<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Messaging\Conversation;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConversationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any conversations.
     */
    public function viewAny(User $user): bool
    {
        return true; // Any authenticated user can view their conversations list
    }

    /**
     * Determine if the user can view the conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->canAccessConversation($user, $conversation);
    }
    
    /**
     * Determine if the user can update the conversation.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $this->canAccessConversation($user, $conversation);
    }

    /**
     * Determine if user can access the conversation.
     */
    private function canAccessConversation(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->user_one 
            || $user->id === $conversation->user_two 
            || $user->isModerator();
    }
}
