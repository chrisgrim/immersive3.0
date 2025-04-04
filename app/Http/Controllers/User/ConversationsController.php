<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Messaging\Conversation;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\Message;

class ConversationsController extends Controller
{
    public function index()
    {
        $conversations = Conversation::where(function($query) {
            $query->where('user_one', auth()->id())
                  ->orWhere('user_two', auth()->id());
        })
        ->with([
            'conversable', 
            'messages' => function($query) {
                $query->latest()
                      ->limit(20);
            },
            'userone',
            'usertwo'
        ])
        ->latest('updated_at')
        ->limit(50)
        ->get();

        return view('user.inbox', compact('conversations'));
    }

    public function show(Conversation $conversation)
    {
        $this->authorize('view', $conversation);
        
        // Mark messages as read and reset unread status for current user
        $conversation->messages()
            ->where('is_seen', false)
            ->where('user_id', '!=', auth()->id())
            ->update(['is_seen' => true]);
        
        auth()->user()->update(['unread' => null]);

        return $conversation->load([
            'conversable',
            'messages' => function($query) {
                $query->orderBy('created_at', 'asc')
                      ->with('user')
                      ->limit(100);
            },
            'userone',
            'usertwo'
        ]);
    }

    /**
     * Get conversation ID that likely has unread messages for current user
     */
    protected function getUnreadConversationId()
    {
        $latestConversation = Conversation::whereHas('messages', function($query) {
            $query->where('is_seen', false)
                  ->where('user_id', '!=', auth()->id());
        })
        ->where(function($query) {
            $query->where('user_one', auth()->id())
                  ->orWhere('user_two', auth()->id());
        })
        ->latest('updated_at')
        ->first();

        return $latestConversation ? $latestConversation->id : null;
    }

    public function update(Request $request, Conversation $conversation)
    {   
        $this->authorize('update', $conversation);
        
        // Sanitize the input
        $sanitizedMessage = htmlspecialchars($request->message, ENT_QUOTES, 'UTF-8');
        
        $receiver = $conversation->user_one === auth()->id() 
            ? $conversation->usertwo 
            : $conversation->userone;

        $latestMessage = $conversation->latestMessages()->first();

        if ($this->canAppendMessage($latestMessage)) {
            $currentMessage = trim($latestMessage->message);
            if (str_ends_with($currentMessage, '</p>')) {
                $currentMessage = substr($currentMessage, 0, -4);
                $updatedMessage = $currentMessage . "<br>" . $sanitizedMessage . "</p>";
            } else {
                $updatedMessage = "<p>" . $sanitizedMessage . "<br>" . $sanitizedMessage . "</p>";
            }
            
            $latestMessage->update([
                'message' => $updatedMessage
            ]);
        } else {
            $conversation->messages()->create([
                'message' => "<p>" . $sanitizedMessage . "</p>",
                'user_id' => auth()->id(),
            ]);
        }

        $this->notifyReceiver($receiver, $sanitizedMessage, $conversation);
        $conversation->touch();
        
        return $conversation->fresh()->load([
            'conversable',
            'messages' => function($query) {
                $query->orderBy('created_at', 'asc');
            },
            'userone',
            'usertwo'
        ]);
    }

    public function events(Request $request)
    {
        $query = Conversation::where(function($query) {
            $query->where('user_one', auth()->id())
                  ->orWhere('user_two', auth()->id());
        })
        ->where('conversable_type', 'App\\Models\\Event')
        ->with('conversable');

        if ($request->search) {
            $query->where('subject', 'LIKE', "%$request->search%");
        }

        return $query->orderBy('updated_at', 'desc')
                     ->limit(20)
                     ->get();
    }

    public function search(Request $request)
    {
        $query = Conversation::where(function($query) {
            $query->where('user_one', auth()->id())
                  ->orWhere('user_two', auth()->id());
        })
        ->with([
            'conversable', 
            'messages' => function($query) {
                $query->latest()
                      ->limit(20);
            },
            'userone',
            'usertwo'
        ]);

        if ($request->search) {
            $query->where('subject', 'LIKE', "%{$request->search}%");
        }

        return $query->latest('updated_at')
                     ->limit(50)
                     ->get();
    }

    protected function canAppendMessage($message)
    {
        $oneMinuteAfter = Carbon::parse($message->created_at)->addMinute();
        return Carbon::now()->isBetween($message->created_at, $oneMinuteAfter)
               && $message->user_id === auth()->id();
    }

    protected function notifyReceiver($receiver, $message, $conversation)
    {
        if (!$receiver) {
            return;
        }
        
        // Always update unread status when a new message is sent
        $receiver->update(['unread' => 'm']);
        
        // Only send email notification if their unread status was null (meaning they've read previous messages)
        if ($receiver->unread === null) {
            $attributes = [
                'email' => $receiver->email,
                'receiver' => $receiver->name,
                'body' => "You have a new message about " . $conversation->subject,
                'sender' => auth()->user()->name,
                'subject' => $conversation->subject,
                'app_url' => config('app.url'),
                'id' => $conversation->id
            ];

            try {
                Mail::to($receiver->email)->send(new Message($attributes));
            } catch (\Exception $e) {
                // Keep this one error log for critical failures
                \Log::error('Failed to send email: ' . $e->getMessage());
            }
        }
    }
}
