<?php

namespace App\Support\Projects;

use App\Models\Event;
use App\Models\Project;
use App\Scopes\LatestPublishedFirstScope;

/**
 * The listing page's "Part of Ha Ha Ha. Also in New York and on Zoom." "Also
 * in" names up to two other runs that are running or coming up. An embargoed
 * run does not count until it is published.
 */
class ProjectLine
{
    public const ALSO_IN = 2;

    /** Platforms that are not a place to be "on". Anything else reads "on Zoom". */
    private const PLATFORM_PHRASES = [
        'web browser' => 'online',
        'web site' => 'online',
        'website' => 'online',
        'computer' => 'online',
        'telephone' => 'by phone',
        'physical mail' => 'by mail',
        'email' => 'by email',
        'sms/text message' => 'by text',
        'podcast/mp3' => 'as a podcast',
        'app' => 'in an app',
        'video conferencing' => 'by video call',
    ];

    /** @return array{project: Project, also: ?string}|null */
    public static function for(Event $event): ?array
    {
        $project = $event->project;

        if ($project === null) {
            return null;
        }

        $others = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->where('project_id', $project->id)
            ->whereKeyNot($event->id)
            ->where('status', Event::STATUS_PUBLISHED)
            ->stillRunning()
            ->with(['location', 'remotelocations'])
            ->orderBy('closingDate')
            ->orderBy('id')
            ->limit(20)
            ->get();

        return ['project' => $project, 'also' => self::phrase($others)];
    }

    private static function phrase($runs): ?string
    {
        $places = $runs->map(fn (Event $run) => self::place($run))->filter()->unique('name')->take(self::ALSO_IN)->values();

        if ($places->isEmpty()) {
            return null;
        }

        $cities = $places->where('kind', 'city')->pluck('name');
        $parts = [];

        if ($cities->isNotEmpty()) {
            $parts[] = 'in '.$cities->join(' and ');
        }

        foreach ($places->where('kind', 'platform') as $place) {
            $parts[] = self::PLATFORM_PHRASES[mb_strtolower($place['name'])] ?? 'on '.$place['name'];
        }

        if ($places->contains('kind', 'online')) {
            $parts[] = 'online';
        }

        return 'Also '.implode(' and ', array_unique($parts)).'.';
    }

    /** @return array{kind: string, name: string}|null */
    private static function place(Event $run): ?array
    {
        if ($run->hasLocation) {
            $city = ucfirst((string) $run->location?->city);

            return $city !== '' ? ['kind' => 'city', 'name' => $city] : null;
        }

        return $run->remotelocations->count() === 1
            ? ['kind' => 'platform', 'name' => ucfirst((string) $run->remotelocations->first()->name)]
            : ['kind' => 'online', 'name' => 'online'];
    }
}
