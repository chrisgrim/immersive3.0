<?php

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Conversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_one',
        'user_two',
        'subject',
        'conversable_type',
        'conversable_id'
    ];
    
    public function conversable()
    {
        return $this->morphTo();
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('updated_at', 'ASC');
    }

    public function latestMessages()
    {
        return $this->hasMany(Message::class)->orderBy('updated_at', 'DESC');
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