<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackClick extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'organizer_id', 'user_id'];
}
