<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to keep a venue's page (VenueClaims). status, processed_by,
 * processed_at and admin_notes are set by hand in VenueClaims, never mass
 * assigned, so a crafted request cannot approve itself.
 */
class VenueClaim extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = ['venue_id', 'user_id', 'message'];

    protected $casts = ['processed_at' => 'datetime'];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
