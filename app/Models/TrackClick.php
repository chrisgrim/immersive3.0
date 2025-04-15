<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 
        'organizer_id', 
        'user_id',
        'ip_address',
        'user_agent',
        'referer_url',
        'click_type',
        'destination_url'
    ];
    
}
