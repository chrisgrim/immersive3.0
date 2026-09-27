<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSuggestion extends Model
{
    public const MAX_LENGTH = 500;

    /**
     * Only what the public form may set. status / processed_by / processed_at are
     * assigned directly by the admin controller, never from request input.
     */
    protected $fillable = [
        'message',
        'user_id',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
