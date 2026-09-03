<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The audit trail of the MCP endpoint — see RecordMcpToolCall.
 */
class McpToolCall extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    /** Rows older than this are pruned nightly (model:prune, ScheduleServiceProvider). */
    public const RETENTION_DAYS = 180;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * An activity-and-address record should not be kept forever.
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
