<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OwnershipClaim extends Model
{
    use HasFactory;

    /**
     * Only fields a user is allowed to set. status / processed_by / processed_at /
     * admin_notes are set by direct assignment in the service — never mass-assigned
     * from request input, so a crafted body can't self-approve a claim.
     */
    protected $fillable = [
        'organizer_id',
        'user_id',
        'message',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
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
