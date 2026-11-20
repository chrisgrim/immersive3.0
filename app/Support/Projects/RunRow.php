<?php

namespace App\Support\Projects;

use App\Enums\RunType;
use App\Models\Event;
use App\Models\Events\Show;
use Carbon\Carbon;

/**
 * One run as the project and venue pages list it: city or platform, dates and
 * presenter. Runs have no public word of their own. A secret-location run
 * shows its city only: placeLabel() never reads the street.
 */
class RunRow
{
    public const NOW = 'now';

    public const COMING = 'coming';

    public const PAST = 'past';

    /** @return array<string, mixed> */
    public static function for(Event $run, ?string $showName = null): array
    {
        $start = InstanceAddress::startDay($run);

        return [
            'id' => $run->id,
            'url' => $run->publicPath(),
            // Only when it goes by a name of its own.
            'name' => $run->name !== $showName ? $run->name : null,
            'place' => self::place($run),
            // Online runs name a platform, not a city.
            'in_person' => (bool) $run->hasLocation,
            'dates' => self::dates($run, $start),
            'start' => $start,
            // The closing day, a plain date in the run's own timezone.
            'end' => $run->closingDate ? Carbon::parse($run->closingDate)->toDateString() : null,
            'bucket' => self::bucket($run, $start),
            'presenter' => $run->organizer,
            'run_type' => RunType::tryFrom((string) $run->run_type)?->label(),
            'festival' => $run->festival,
            'image' => $run->images->firstWhere('rank', 0)?->thumb_image_path ?? $run->thumbImagePath,
            // The card grids show the full picture; the thumb is too small for them.
            'large_image' => $run->images->firstWhere('rank', 0)?->large_image_path ?? $run->largeImagePath,
        ];
    }

    public static function place(Event $run): string
    {
        if ($run->hasLocation) {
            return $run->location?->placeLabel() ?? '';
        }

        return $run->remotelocations->count() === 1
            ? ucfirst((string) $run->remotelocations->first()->name)
            : 'Online';
    }

    private static function dates(Event $run, string $start): string
    {
        if ($run->showtype === 'a') {
            return 'Always available';
        }

        if ($run->showtype === 'l') {
            return 'Limited availability';
        }

        $first = Carbon::parse($start);
        $last = $run->closingDate ? Carbon::parse($run->closingDate) : $first;

        return match (true) {
            $first->isSameDay($last) => $first->format('M j, Y'),
            $first->year === $last->year => $first->format('M j').' to '.$last->format('M j, Y'),
            default => $first->format('M j, Y').' to '.$last->format('M j, Y'),
        };
    }

    private static function bucket(Event $run, string $start): string
    {
        if (! $run->isShowing) {
            return self::PAST;
        }

        return $start > Carbon::now(Show::validTimezone($run->timezone))->toDateString() ? self::COMING : self::NOW;
    }
}
