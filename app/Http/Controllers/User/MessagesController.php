<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use Illuminate\Http\Request;

class MessagesController extends Controller
{
    /**
     * Check if the current user has any unread messages
     *
     * @return \Illuminate\Http\Response
     */
    public function checkUnread()
    {
        $hasUnread = auth()->user()->hasUnreadMessages();
        
        $unreadMessages = Conversation::where(function($query) {
            $query->where('user_one', auth()->id())
                  ->orWhere('user_two', auth()->id());
        })
        ->whereHas('messages', function($query) {
            $query->where('is_seen', false)
                 ->where('user_id', '!=', auth()->id());
        })
        ->count();
        
        return response()->json([
            'has_unread' => $hasUnread,
            'unread_count' => $unreadMessages
        ]);
    }
    
    /**
     * Mark all messages as read for the current user
     *
     * @return \Illuminate\Http\Response
     */
    public function markAllRead()
    {
        // Find all conversations where user is a participant
        $conversations = Conversation::where(function($query) {
            $query->where('user_one', auth()->id())
                  ->orWhere('user_two', auth()->id());
        })->get();
        
        // For each conversation, mark messages from other users as seen
        foreach ($conversations as $conversation) {
            $conversation->messages()
                ->where('is_seen', false)
                ->where('user_id', '!=', auth()->id())
                ->update(['is_seen' => true]);
        }
        
        // Reset user's unread flag
        auth()->user()->update(['unread' => null]);
        
        return response()->json(['status' => 'success']);
    }
}
