<?php

namespace App\Models\Messaging;

use App\Models\User;
use App\Models\Event;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['message', 'user_id', 'conversation_id','is_seen', 'deleted_from_sender','deleted_from_receiver'];

    /**
    * The relations to eager load on every query. I am adding shows here because I need to filter by dates for the search
    *
    * @var array
    */
    protected $with = ['user'];

    public const MESSAGES = [
        'APPROVED' => 'Your event has been approved!',
        'APPROVED_EMBARGOED' => 'Your event has been approved and will be displayed on your chosen date.',
        'REJECTED' => 'We have reviewed your event submission and have some feedback that needs to be addressed. Please see the details below:',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event() 
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Create a notification message for an event
     *
     * @param Event $event
     * @param string $message
     * @param string $slug
     * @return Message
     */
    public static function eventnotification(Event $event, string $message, string $slug): Message
    {
        $adminId = auth()->id();

        // Find existing conversation or create new one
        $conversation = Conversation::firstOrCreate(
            [
                'event_id' => $event->id,
                'user_one' => $adminId,
                'user_two' => $event->user_id
            ],
            [
                'event_name' => $event->name
            ]
        );

        // Create the notification message
        $message = self::create([
            'conversation_id' => $conversation->id,
            'user_id' => $adminId,
            'message' => $message,
            'is_seen' => false,
            'deleted_from_sender' => false,
            'deleted_from_receiver' => false
        ]);

        return $message;
    }
}
