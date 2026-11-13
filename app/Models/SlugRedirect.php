<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An old public address and the record it belongs to. Every row reserves its
 * path: nothing but that record may ever go by it. A record that takes one of
 * its own old addresses back drops the row in the same write (forget()), so no
 * redirect ever points at the address it is reached by.
 *
 * Paths have no leading slash: events/{slug}, events/{project}/{instance},
 * organizers/{slug}, venues/{slug}.
 */
class SlugRedirect extends Model
{
    protected $fillable = ['path', 'redirectable_type', 'redirectable_id'];

    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Whether $path is reserved for anything but this one record. */
    public static function pointsElsewhere(string $path, string $type, ?int $id): bool
    {
        return static::where('path', $path)
            ->where(fn ($q) => $q->where('redirectable_type', '!=', $type)
                ->orWhere('redirectable_id', '!=', (int) $id))
            ->exists();
    }

    /** Point $path at $record, whoever held it before. */
    public static function remember(string $path, Model $record): void
    {
        static::updateOrCreate(
            ['path' => $path],
            ['redirectable_type' => $record->getMorphClass(), 'redirectable_id' => $record->getKey()],
        );
    }

    /** Everything that led to $from leads to $to now. */
    public static function repoint(Model $from, Model $to): void
    {
        static::where('redirectable_type', $from->getMorphClass())
            ->where('redirectable_id', $from->getKey())
            ->update(['redirectable_type' => $to->getMorphClass(), 'redirectable_id' => $to->getKey()]);
    }

    /** $record lives at $path now, so its alias there goes. Other records' rows stay. */
    public static function forget(string $path, Model $record): void
    {
        static::where('path', $path)
            ->where('redirectable_type', $record->getMorphClass())
            ->where('redirectable_id', $record->getKey())
            ->delete();
    }

    /**
     * Where this old address leads now: [path, status], or null when it leads
     * nowhere a visitor may go. An event that is not published answers null,
     * so an old address confirms nothing about it, unless it was deleted and
     * its show lives on: a deleted run's addresses fall back to the show.
     *
     * @return array{0: string, 1: int}|null
     */
    public function destination(): ?array
    {
        $record = $this->target();

        if ($record instanceof Event) {
            if ($record->trashed()) {
                $project = $record->project_id ? Project::find($record->project_id) : null;

                return $project ? [$project->publicPath(), 302] : null;
            }

            return $record->status === Event::STATUS_PUBLISHED ? [$record->publicPath(), 301] : null;
        }

        if ($record === null || ($record instanceof Project && $record->trashed())) {
            return null;
        }

        // Like an event: an organizer or venue nobody may visit yet answers
        // null, so its old address doesn't give away its new one.
        if (($record instanceof Organizer && $record->status !== 'p')
            || ($record instanceof Venue && $record->status !== Venue::APPROVED)) {
            return null;
        }

        return [$record->publicPath(), 301];
    }

    /** The record, a soft-deleted one included; destination() decides what that means. */
    public function target(): ?Model
    {
        $class = $this->redirectable_type;

        if (! class_exists($class)) {
            return null;
        }

        $query = in_array(SoftDeletes::class, class_uses_recursive($class), true)
            ? $class::withTrashed()
            : $class::query();

        return $query->find($this->redirectable_id);
    }
}
