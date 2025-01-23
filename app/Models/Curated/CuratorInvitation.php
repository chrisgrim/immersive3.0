<?php

namespace App\Models\Curated;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CuratorInvitation extends Model
{
    protected $fillable = [
        'community_id',
        'email',
        'token',
        'expires_at',
        'accepted_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}