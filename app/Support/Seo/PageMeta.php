<?php

namespace App\Support\Seo;

use App\Models\Event;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The <title> and meta description Google shows for a page.
 *
 * Searches that reach an event are mostly "show + city + when/tickets", so
 * the title says the city and the dates and the description says venue,
 * dates and price before the organizer's own words. Titles stay short
 * enough to show whole (Google cuts near 60 characters): the brand goes
 * first when they are long, then the dates; the name is never cut.
 * og:title stays the bare name everywhere (a link preview has its own card).
 */
class PageMeta
{
    public const BRAND = 'Everything Immersive';

    /** Past this, Google's results cut the title off. */
    public const TITLE_MAX = 65;

    public const DESCRIPTION_MAX = 160;

    public static function eventTitle(Event $event): string
    {
        $name = trim((string) $event->name);
        $city = self::eventCity($event);

        // "The Drunken Lab Houston" already says where it is.
        $head = $city && ! Str::contains(Str::lower($name), Str::lower($city))
            ? "{$name} in {$city}"
            : $name;

        return self::fit($head, self::eventDates($event, short: true));
    }

    public static function eventDescription(Event $event, ?string $venueName = null): string
    {
        // A secret location's venue is never named (publicVenueName()).
        $venueName ??= $event->location?->publicVenueName();

        $where = $event->hasLocation
            ? implode(', ', array_filter([$venueName, $event->location?->placeLabel()]))
            : 'Online';

        $facts = array_filter([
            $where ?: null,
            self::eventDates($event, short: false),
            $event->price_range ? 'Tickets '.$event->price_range : null,
        ]);

        // A tagline that only repeats the name ("Absurd City - Absurd City, 80,000
        // sq ft...") gives way to the description's first sentence.
        $tagline = self::plain($event->tag_line);
        $words = $tagline && ! Str::startsWith(Str::lower($tagline), Str::lower(trim((string) $event->name)))
            ? $tagline
            : (self::firstSentence($event->description) ?: $tagline);

        return self::sentences([...$facts, $words]);
    }

    /** "Run name: 3 cities including London, Paris, New York" for a show with many runs. */
    public static function projectTitle(Project $project, array $page): string
    {
        $cities = self::runPlaces($page);
        $count = $cities->count();

        $tail = match (true) {
            $count === 0 => 'Dates and tickets',
            $count === 1 => $cities->first(),
            default => "{$count} cities incl. ".$cities->take(2)->implode(', '),
        };

        return self::fit($project->name, $tail, joiner: ': ');
    }

    public static function projectDescription(Project $project, array $page): string
    {
        $cities = self::runPlaces($page);
        $more = $cities->count() - 4;
        $where = $cities->take(4)->implode(', ').($more > 0 ? " and {$more} more" : '');

        $price = collect($page['from'] ?? [])->first();

        return self::sentences([
            $cities->isNotEmpty() ? 'On now or coming up in '.$where : null,
            $price,
            self::firstSentence($project->description),
        ]);
    }

    /** "Organizer: immersive shows and events | Everything Immersive" */
    public static function organizerTitle(string $name): string
    {
        return self::fit(trim($name), 'Immersive shows and events', joiner: ': ');
    }

    public static function venueTitle(string $name, ?string $place): string
    {
        return self::fit(trim($name), $place ?: 'Immersive shows and events', joiner: ', ');
    }

    /**
     * When the event is on, short for the title ("Until Jan 4, 2027") or
     * long for the description ("Nov 20, 2026 to Jan 4, 2027").
     */
    public static function eventDates(Event $event, bool $short): ?string
    {
        if ($event->showtype === 'a') {
            return $short ? null : 'Always available';
        }

        $end = $event->closingDate ? Carbon::parse($event->closingDate) : null;
        $firstStored = $event->show_summary['first_date'] ?? null;
        $start = $firstStored ? Carbon::parse($event->localDate($firstStored)) : null;

        if (! $end) {
            return null;
        }

        $today = Carbon::now(\App\Models\Events\Show::validTimezone($event->timezone))->startOfDay();

        if ($end->lt($today)) {
            return 'Ended '.$end->format('M j, Y');
        }

        if ($start && $start->gt($today)) {
            if ($start->isSameDay($end)) {
                return $start->format('M j, Y');
            }

            return ($start->year === $end->year ? $start->format('M j') : $start->format('M j, Y')).' to '.$end->format('M j, Y');
        }

        return ($short ? 'Until ' : 'On now until ').$end->format('M j, Y');
    }

    private static function eventCity(Event $event): ?string
    {
        if (! $event->hasLocation) {
            return null;
        }

        $city = trim((string) $event->location?->city);

        return $city !== '' ? Str::ucfirst($city) : null;
    }

    /** @return Collection<int, string> the cities of the runs on now, then coming up */
    private static function runPlaces(array $page): Collection
    {
        return collect($page['now'] ?? [])->concat($page['coming'] ?? [])
            ->map(fn ($run) => trim(Str::before((string) $run['place'], ',')))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * "{head}{joiner}{tail} | Everything Immersive", dropping the brand and
     * then the tail until it fits. The head (the page's own name) is never cut.
     */
    public static function fit(string $head, ?string $tail, string $joiner = ' · '): string
    {
        $suffix = ' | '.self::BRAND;
        $withTail = $tail ? $head.$joiner.$tail : $head;

        foreach ([$withTail.$suffix, $withTail, $head.$suffix] as $candidate) {
            if (mb_strlen($candidate) <= self::TITLE_MAX) {
                return $candidate;
            }
        }

        return $head;
    }

    /** Joins the parts as sentences, then trims to a word boundary under the limit. */
    private static function sentences(array $parts): string
    {
        $text = collect($parts)
            ->map(fn ($part) => self::plain($part))
            ->filter()
            ->map(fn ($part) => Str::ucfirst($part))
            ->map(fn ($part) => preg_match('/[.!?…]$/u', $part) ? $part : $part.'.')
            ->implode(' ');

        return self::trimWords($text, self::DESCRIPTION_MAX);
    }

    private static function firstSentence(?string $text): ?string
    {
        $text = self::plain($text);

        if (! $text) {
            return null;
        }

        return preg_match('/^(.{20,}?[.!?])(\s|$)/u', $text, $m) ? $m[1] : $text;
    }

    private static function plain(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
    }

    /** Cuts at the last whole word that fits, never mid-word. */
    public static function trimWords(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space ? mb_substr($cut, 0, $space) : $cut, ' ,;:.-').'…';
    }
}
