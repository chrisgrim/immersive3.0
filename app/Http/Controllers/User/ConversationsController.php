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
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index()
    {
        $conversations = Conversation::where(function($query) {
            $query->where('user_one', auth()->id())
                  ->orWhere('user_two', auth()->id());
        })
        ->with([
            'event', 
            'messages' => function($query) {
                $query->orderBy('created_at', 'desc');
            },
            'userone',
            'usertwo'
        ])
        ->orderBy('updated_at', 'desc')
        ->get();

        return view('User.inbox', compact('conversations'));
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
            'event',
            'messages' => function($query) {
                $query->orderBy('created_at', 'asc')
                      ->with('user');
            },
            'userone',
            'usertwo'
        ]);
    }

    public function update(Request $request, Conversation $conversation)
    {   
        $this->authorize('update', $conversation);
        
        $receiver = $conversation->user_one === auth()->id() 
            ? $conversation->usertwo 
            : $conversation->userone;

        $latestMessage = $conversation->latestMessages()->first();

        if ($this->canAppendMessage($latestMessage)) {
            $latestMessage->update([
                'message' => "{$latestMessage->message} {$request->message}"
            ]);
        } else {
            $conversation->messages()->create([
                'message' => $request->message,
                'user_id' => auth()->id(),
            ]);
        }

        $this->notifyReceiver($receiver, $request->message, $conversation);
        $conversation->touch();
        
        return $conversation->fresh()->load([
            'event',
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
        })->with('event');

        if ($request->search) {
            $query->where('event_name', 'LIKE', "%$request->search%");
        }

        return $query->orderBy('updated_at', 'desc')
                     ->limit(20)
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

        // Check if all previous messages have been seen
        $allMessagesSeen = !$conversation->messages()
            ->where('user_id', '!=', $receiver->id)
            ->where('is_seen', false)
            ->where('id', '!=', $conversation->messages()->latest()->first()->id) // Exclude the message we just created
            ->exists();

        // Send email if all previous messages were seen (meaning they viewed the conversation)
        if ($allMessagesSeen) {
            $attributes = [
                'email' => $receiver->email,
                'receiver' => $receiver->name,
                'body' => 'You have a new message about your event.',
                'sender' => auth()->user()->name,
                'event' => $conversation->event_name,
                'app_url' => config('app.url'),
                'id' => $conversation->id
            ];

            Mail::to($receiver->email)->send(new Message($attributes));
        }

        $receiver->update(['unread' => 'e']);
    }
}
