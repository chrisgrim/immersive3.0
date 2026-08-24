<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    use HasFactory;

    // last_checked_at IS fillable — every write path that sets it
    // (UpdateSavedSearchAction, SavedSearchController::toggleNotify,
    // NotifySavedSearchMatchesCommand) uses Eloquent's update()/create(),
    // which enforce $fillable same as mass assignment does. Excluding it
    // here would silently no-op those writes too, not just block a
    // hypothetical malicious client — and no controller in this app ever
    // mass-assigns a raw request body onto this model (each one extracts
    // specific fields explicitly), so there's no real exposure to guard
    // against by excluding it.
    protected $fillable = [
        'user_id', 'name', 'criteria', 'fingerprint', 'pinned', 'is_scratch', 'notify_new_events', 'last_checked_at',
    ];

    protected $casts = [
        'criteria' => 'array',
        'pinned' => 'boolean',
        'is_scratch' => 'boolean',
        'notify_new_events' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Invariant: a pinned row is never scratch. Enforced here rather than
     * trusted to every caller (controller, action, factory, tinker) so it
     * can't be forgotten — SaveSearchAction's reclaim query relies on
     * is_scratch alone (not pinned) to decide what's safe to overwrite, so
     * a pinned-but-still-scratch row would be silently destroyable despite
     * looking protected in the UI.
     */
    protected static function booted(): void
    {
        static::saving(function (SavedSearch $search) {
            if ($search->pinned) {
                $search->is_scratch = false;
            }
        });
    }
}
