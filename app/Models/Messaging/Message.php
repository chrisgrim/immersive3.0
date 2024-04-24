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

}
