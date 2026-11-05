<?php

use App\Actions\Projects\LinkEventToProject;
use App\Models\Event;
use App\Models\Events\RemoteLocation;
use App\Models\Events\Show;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Support\Projects\InstanceAddress;
use App\Support\ShowHistory;
use Carbon\Carbon;
use Illuminate\Support\Str;

function emptyProject(): Project
{
    return Project::factory()->create(['slug' => 'ha-ha-ha']);
}

function onlineListing(array $platforms, array $overrides = []): Event
{
    $event = publishedListing('Ha Ha Ha Online', 'Nowhere', array_merge(['hasLocation' => false, 'attendance_type_id' => 2], $overrides));

    foreach ($platforms as $name) {
        $platform = RemoteLocation::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'user_id' => $event->user_id]);
        $event->remotelocations()->attach($platform);
    }

    return $event;
}

test('an in-person run is city and year', function () {
    expect(InstanceAddress::allocate(publishedListing('A', 'New York'), emptyProject()))->toBe('new-york-2026');
});

test('an online run on one platform uses its slug, on several the word online', function () {
    $project = emptyProject();

    expect(InstanceAddress::allocate(onlineListing(['Zoom']), $project))->toBe('zoom-2026')
        ->and(InstanceAddress::allocate(onlineListing(['Zoom', 'Discord']), $project))->toBe('online-2026');
});

test('an older platform slug with a slash in it stays one address segment', function () {
    $event = onlineListing(['Discord']);
    $event->remotelocations()->first()->update(['slug' => 'discord/slack']);

    expect(InstanceAddress::allocate($event, emptyProject()))->toBe('discord-slack-2026');
});

test('an in-person listing with no usable city is still a valid address', function () {
    expect(InstanceAddress::allocate(publishedListing('A', '東京'), emptyProject()))->toBe('2026');
});

test('an edition goes after the year', function () {
    $event = publishedListing('A', 'Chicago', ['edition' => 'Holiday Edition']);

    expect(InstanceAddress::allocate($event, emptyProject()))->toBe('chicago-2026-holiday-edition');
});

test('the year is the earliest real day in the show rows, read in the event\'s own timezone', function () {
    // Midnight UTC on January 1st with curtain times elsewhere in the schedule
    // is still December 31st in Chicago.
    $event = publishedListing('A', 'Chicago', [], null, '2027-01-01 02:00:00');
    Show::factory()->create(['event_id' => $event->id, 'date' => '2027-03-01 19:00:00']);

    expect(InstanceAddress::allocate($event, emptyProject()))->toBe('chicago-2026');
});

test('a date-only row is its own calendar day, whatever the timezone', function () {
    $event = publishedListing('A', 'Chicago', ['timezone' => 'Asia/Tokyo'], null, '2027-01-01 00:00:00');

    expect(InstanceAddress::allocate($event, emptyProject()))->toBe('chicago-2027');
});

test('days folded into the show history count towards the first day', function () {
    $event = publishedListing('A', 'Chicago', [], null, '2026-12-10 00:00:00');
    $event->forceFill(['show_history' => ShowHistory::pack(['2019-05-04', '2019-05-11'], '2020-01-01')])->save();

    expect(InstanceAddress::allocate($event->fresh(), emptyProject()))->toBe('chicago-2019');
});

test('an always-available run ignores its sentinel row and takes the approval year', function () {
    $event = publishedListing('A', 'Chicago', ['showtype' => 'a', 'published_at' => '2024-06-01 12:00:00'], null, '2099-12-31 00:00:00');

    expect(InstanceAddress::allocate($event, emptyProject()))->toBe('chicago-2024');
});

test('a limited run does too', function () {
    $event = publishedListing('A', 'Chicago', ['showtype' => 'l', 'published_at' => '2023-06-01 12:00:00'], null, '2099-12-31 00:00:00');

    expect(InstanceAddress::allocate($event, emptyProject()))->toBe('chicago-2023');
});

test('an embargoed always-available run takes the embargo year', function () {
    $event = publishedListing('A', 'Chicago', [
        'showtype' => 'a',
        'status' => 'e',
        'published_at' => '2026-11-01 12:00:00',
        'embargo_date' => '2027-01-15 12:00:00',
    ], null, '2099-12-31 00:00:00');

    expect(InstanceAddress::allocate($event, emptyProject()))->toBe('chicago-2027');
});

test('the approval year is read in the event\'s own timezone', function () {
    $event = publishedListing('A', 'Chicago', ['showtype' => 'a', 'published_at' => '2027-01-01 03:00:00'], null, '2099-12-31 00:00:00');

    expect(InstanceAddress::allocate($event, emptyProject()))->toBe('chicago-2026');
});

test('an address a live run holds gets a number', function () {
    $project = emptyProject();
    publishedListing('A', 'Chicago')->update(['project_id' => $project->id, 'instance_slug' => 'chicago-2026']);

    expect(InstanceAddress::allocate(publishedListing('B', 'Chicago'), $project))->toBe('chicago-2026-2');
});

test('an address in slug_redirects for something else is refused', function () {
    $project = emptyProject();
    $other = publishedListing('A', 'Paris');
    SlugRedirect::remember('events/ha-ha-ha/chicago-2026', $other);
    SlugRedirect::remember('events/ha-ha-ha/chicago-2026-2', $other);

    expect(InstanceAddress::allocate(publishedListing('B', 'Chicago'), $project))->toBe('chicago-2026-3');
});

test('its own old address is not refused', function () {
    $project = emptyProject();
    $event = publishedListing('B', 'Chicago');
    SlugRedirect::remember('events/ha-ha-ha/chicago-2026', $event);

    expect(InstanceAddress::allocate($event, $project))->toBe('chicago-2026');
});

test('the unique index stops two runs sharing an address, and plain listings do not collide', function () {
    $project = emptyProject();
    publishedListing('A', 'Chicago')->update(['project_id' => $project->id, 'instance_slug' => 'chicago-2026']);

    expect(fn () => publishedListing('B', 'Paris')->update(['project_id' => $project->id, 'instance_slug' => 'chicago-2026']))
        ->toThrow(Illuminate\Database\QueryException::class);

    expect(Event::whereNull('project_id')->count())->toBeGreaterThan(0);
    publishedListing('C', 'Paris');
    expect(Event::whereNull('project_id')->whereNull('instance_slug')->count())->toBeGreaterThan(1);
});

// ----- public_url -----

test('a plain listing\'s public_url is its slug address, and its JSON gains nothing else', function () {
    $event = publishedListing('Plain', 'Chicago');

    $json = $event->fresh()->toArray();

    expect($json['public_url'])->toBe('/events/plain')
        ->and($json)->not->toHaveKey('project')
        ->and($json['project_id'])->toBeNull();
});

test('a linked run\'s public_url is its address under the show', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->actingAs(staffModerator());
    app(LinkEventToProject::class)->link(publishedListing('Ha Ha Ha', 'New York'), $first, auth()->user());

    expect($first->fresh()->toArray()['public_url'])->toBe('/events/ha-ha-ha/chicago-2026');
});

test('serializing a page of cards costs one project query, and a page of plain ones none', function () {
    $project = emptyProject();
    foreach (['A', 'B', 'C', 'D'] as $i => $name) {
        publishedListing($name, 'Chicago')->update(['project_id' => $project->id, 'instance_slug' => 'chicago-'.$i]);
    }

    $queries = collect();
    Illuminate\Support\Facades\DB::listen(fn ($q) => $queries->push($q->sql));

    Event::query()->get()->toArray();

    expect($queries->filter(fn ($sql) => str_contains($sql, '`projects`'))->count())->toBe(1);

    Event::query()->update(['project_id' => null, 'instance_slug' => null]);
    $queries->splice(0);

    Event::query()->get()->toArray();

    expect($queries->filter(fn ($sql) => str_contains($sql, '`projects`'))->count())->toBe(0);
});

test('a narrow select without a slug serializes no address rather than a broken one', function () {
    publishedListing('A', 'Chicago');

    expect(Event::query()->select('id', 'name')->first()->toArray()['public_url'])->toBeNull();
});

test('the year is stable: Carbon now does not leak into dated runs', function () {
    Carbon::setTestNow('2031-06-01 12:00:00');

    expect(InstanceAddress::allocate(publishedListing('A', 'Chicago'), emptyProject()))->toBe('chicago-2026');

    Carbon::setTestNow();
});
