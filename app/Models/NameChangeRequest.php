<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NameChangeRequest extends Model
{
    protected $fillable = [
        'current_name',
        'requested_name',
        'status',
        'reason',
        'admin_notes',
        'user_id',
        'processed_by',
        'processed_at'
    ];

    protected $casts = [
        'processed_at' => 'datetime'
    ];

    public function requestable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}