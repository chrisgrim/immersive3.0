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
        $events = auth()->user()->eventconversations()->limit(20)->with('event')->get();
        return view('User.index', compact('events'));
    }

    public function show(Conversation $conversation)
    {
        $this->authorize('update', $conversation);
        auth()->user()->update(['unread' => null]);
        return $conversation->load('event', 'messages');
    }

    public function update(Request $request, Conversation $conversation)
    {   
        $this->authorize('update', $conversation);
        
        $receiver = $conversation->users->firstWhere('id', '!=', auth()->id());
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

        $this->notifyReceiver($receiver, $request->message, $conversation ?? null);

        $conversation->touch();
        return $conversation->load('event', 'messages');
    }

    public function events(Request $request)
    {
        if (! $request->search) return auth()->user()->eventconversations()->limit(20)->with('event')->get();

        return auth()->user()->eventconversations()->where('event_name', 'LIKE', "%$request->search%")->limit(20)->with('event')->get();
    }

    protected function canAppendMessage($message)
    {
        $oneMinuteAfter = Carbon::parse($message->created_at)->addMinute();
        return Carbon::now()->isBetween($message->created_at, $oneMinuteAfter)
               && $message->user_id === auth()->id();
    }

    protected function notifyReceiver($receiver, $message, $conversation)
    {
        $receiver ? $receiver->update(['unread' => 'e']) : '';

        $attributes = [
            'email' => $receiver->email,
            'receiver' => $receiver->name,
            'body' => $message,
            'sender' => auth()->user()->name,
            'event' => $conversation->event_name,
            'app_url' => config('app.url'),
            'id' => $conversation->id
        ];

        Mail::to($receiver->email)->send(new Message($attributes));
        
    }
}
