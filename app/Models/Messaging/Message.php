<?php

namespace App\Models\Messaging;

use App\Models\User;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['message', 'user_id', 'conversation_id', 'is_seen', 'deleted_from_sender', 'deleted_from_receiver'];

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
        'ORGANIZER_REJECTED' => 'We have reviewed your organizer profile and have some feedback that needs to be addressed. Please see the details below:',
        'ORGANIZER_APPROVED' => 'Your organization profile has been approved!',
        'COMMUNITY_REJECTED' => 'We have reviewed your community and have some feedback that needs to be addressed. Please see the details below:',
        'COMMUNITY_APPROVED' => 'Your community has been approved!',
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

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * Create a notification message for any model type
     *
     * @param Model $model The model instance (Event, Organizer, etc.)
     * @param string $message
     * @param string $slug
     * @return Message
     */
    public static function notification(Model $model, string $message, string $slug): Message
    {
        $adminId = auth()->id();

        $conversation = Conversation::firstOrCreate(
            [
                'conversable_type' => get_class($model),
                'conversable_id' => $model->id,
                'user_one' => $adminId,
                'user_two' => $model->user_id
            ],
            [
                'subject' => $model->name
            ]
        );

        return self::create([
            'conversation_id' => $conversation->id,
            'user_id' => $adminId,
            'message' => $message,
            'is_seen' => false,
            'deleted_from_sender' => false,
            'deleted_from_receiver' => false
        ]);
    }
}
