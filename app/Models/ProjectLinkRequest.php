<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A listing asking to join a show: project_id, or source_event_id when the
 * show is still a standalone listing. Decided by LinkEventToProject.
 */
class ProjectLinkRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** Taken back by the listing's team, or overtaken by another link. */
    public const WITHDRAWN = 'withdrawn';

    /** A request the company leaves unanswered this long goes to the moderators. */
    public const ESCALATE_AFTER_HOURS = 72;

    protected $fillable = ['event_id', 'project_id', 'source_event_id', 'requested_by', 'status', 'decided_by', 'decided_at', 'submitted_at', 'escalated_at', 'note'];

    protected $casts = [
        'decided_at' => 'datetime',
        'submitted_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'source_event_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
