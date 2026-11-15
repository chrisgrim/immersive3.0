<?php

namespace App\Models;

use App\Enums\CreditDepartment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One line of a credits list: an artist on a show (Project: writers only) or
 * on a run (Event: everyone else). A run's credits are its own rows plus its
 * project's writing rows.
 */
class Credit extends Model
{
    use HasFactory;

    public const APPROVED = 'approved';

    /** Reserved for a later self-added credit. Never written yet; every reader ignores it. */
    public const PENDING = 'pending';

    protected $fillable = ['creditable_type', 'creditable_id', 'artist_id', 'department', 'role', 'character', 'kind', 'covers', 'credited_as', 'starts_on', 'ends_on', 'note', 'rank', 'status'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(fn (Credit $credit) => $credit->assertDepartmentFits());
    }

    /**
     * Writing is pinned: a project row must be writing, and an event row may
     * be writing only while the event is standalone.
     */
    public function assertDepartmentFits(): void
    {
        $writing = $this->department === CreditDepartment::Writing->value;
        $creditable = $this->creditable;

        if ($creditable instanceof Project && ! $writing) {
            throw new LogicException('A show carries only its writers; other credits belong to a run.');
        }

        if ($creditable instanceof Event && $writing && $creditable->project_id !== null) {
            throw new LogicException('A linked run holds no writers; they belong to its project.');
        }
    }

    public function creditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::APPROVED);
    }

    /** A new row like this one, on $creditable. The same artist, never a second artist row. */
    public function copyOnto(Model $creditable): self
    {
        $copy = $this->replicate();
        $copy->unsetRelation('creditable');
        $copy->creditable()->associate($creditable);
        $copy->save();

        return $copy;
    }

    /**
     * The same line of a run's list: for copying a list onto another, where a
     * cast member and a crew member, two characters, or a regular and an
     * understudy in one role are different credits.
     */
    public function isSameLineAs(self $other): bool
    {
        return $this->isEquivalentTo($other)
            && $this->department === $other->department
            && $this->character === $other->character
            && $this->kind === $other->kind
            && $this->covers === $other->covers;
    }

    /** Same artist, role, billed name and dates: the test for dropping a merged writer. */
    public function isEquivalentTo(self $other): bool
    {
        return $this->artist_id === $other->artist_id
            && $this->role === $other->role
            && $this->credited_as === $other->credited_as
            && $this->starts_on?->toDateString() === $other->starts_on?->toDateString()
            && $this->ends_on?->toDateString() === $other->ends_on?->toDateString();
    }
}
