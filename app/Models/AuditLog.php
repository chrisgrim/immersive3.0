<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Append-only: who linked, approved, changed or merged what. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['subject_type', 'subject_id', 'action', 'user_id', 'details'];

    protected $casts = ['details' => 'array'];

    public static function record(Model $subject, string $action, ?User $user, array $details = []): self
    {
        return static::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'user_id' => $user?->id,
            'details' => $details === [] ? null : $details,
        ]);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
