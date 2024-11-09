<?php

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Model;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    protected $fillable = ['opener_id', 'receiver_id', 'event_id','user_one','user_two', 'event_name'];
    
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('updated_at', 'ASC');
    }

    public function latestMessages()
    {
        return $this->hasMany(Message::class)->orderBy('updated_at', 'DESC');
    }

    public function event()
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function userone()
    {
        return $this->belongsTo(User::class, 'user_one');
    }

    public function usertwo()
    {
        return $this->belongsTo(User::class, 'user_two');
    }

}