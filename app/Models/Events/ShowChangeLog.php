<?php

namespace App\Models\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ShowChangeLog extends Model
{
    protected $fillable = ['event_id', 'user_id', 'action', 'dates'];

    protected $casts = [
        'dates' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
