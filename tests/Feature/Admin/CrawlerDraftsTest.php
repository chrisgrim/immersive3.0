<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\CreateEventDraft;
use App\Mcp\Tools\ListMyEvents;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * The AI crawler (Worker) drafts events over MCP as its own moderator account,
 * under the real organizer. Its drafts are held: out of the organizer's sight
 * until staff approve them, listed in the admin's "AI crawler" section for a
 * moderator to check, fix and submit through the normal flow.
 */
function crawlerSetup(): array
{
    $crawler = User::factory()->create(['type' => 'm', 'email_verified_at' => now()]);
    config(['ei.crawler_user_id' => $crawler->id]);
    $owner = User::factory()->create(['type' => 'g', 'email_verified_at' => now()]);
    $organizer = Organizer::factory()->create(['status' => 'p', 'user_id' => $owner->id]);

    return [$crawler, $organizer, $owner];
}

function heldDraft(Organizer $organizer, User $crawler, array $extra = []): Event
{
    return Event::factory()->create(array_merge(['user_id' => $crawler->id, 'organizer_id' => $organizer->id, 'status' => '0', 'held_for_staff' => true], $extra));
}

it('holds a draft the crawler account creates, and only the crawler\'s', function () {
    [$crawler, $organizer] = crawlerSetup();

    EiServer::actingAs($crawler)->tool(CreateEventDraft::class, ['organizer_id' => $organizer->id, 'name' => 'The Vanishing Hotel'])->assertOk();
    EiServer::actingAs(User::factory()->create(['type' => 'm', 'email_verified_at' => now()]))
        ->tool(CreateEventDraft::class, ['organizer_id' => $organizer->id, 'name' => 'Another Show'])->assertOk();

    $events = Event::withoutGlobalScopes()->where('organizer_id', $organizer->id)->pluck('held_for_staff', 'name');
    expect($events['The Vanishing Hotel'])->toBeTrue()->and($events['Another Show'])->toBeFalse();
});

it('keeps a held draft out of the organizer team\'s sight until it is approved', function () {
    [$crawler, $organizer, $owner] = crawlerSetup();
    $draft = heldDraft($organizer, $crawler, ['name' => 'The Vanishing Hotel']);

    $this->actingAs($owner);
    $this->get("/hosting/event/{$draft->slug}/edit")->assertForbidden();
    $this->get('/hosting/events')->assertOk()->assertDontSee('The Vanishing Hotel');
    EiServer::actingAs($owner)->tool(ListMyEvents::class, [])->assertOk()->assertDontSee('The Vanishing Hotel');
    expect(app(\App\Actions\Events\CheckDuplicateEventNames::class)->handle('The Vanishing Hotel', null, $owner))->toBeNull();

    $draft->update(['status' => 'p']);

    expect($draft->fresh()->held_for_staff)->toBeFalse();
    $this->get("/hosting/event/{$draft->slug}/edit")->assertOk();
    EiServer::actingAs($owner)->tool(ListMyEvents::class, [])->assertOk()->assertSee('The Vanishing Hotel');
});

it('lists held drafts and sent-back events, not submitted or live ones, to moderators', function () {
    [$crawler, $organizer] = crawlerSetup();
    $draft = heldDraft($organizer, $crawler);
    $sentBack = heldDraft($organizer, $crawler, ['status' => 'n']);
    heldDraft($organizer, $crawler, ['status' => 'r']);
    Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'p']);
    Event::factory()->create(['organizer_id' => $organizer->id, 'status' => '0']); // someone else's draft
    heldDraft($organizer, $crawler)->delete();

    $this->actingAs(User::factory()->create(['type' => 'm']));
    $rows = collect($this->getJson('/api/admin/manage/crawler-events')->assertOk()->json('data'))->keyBy('id');

    expect($rows->keys()->sort()->values()->all())->toBe(collect([$draft->id, $sentBack->id])->sort()->values()->all())
        ->and($rows[$sentBack->id]['sent_back'])->toBeTrue()
        ->and($rows[$draft->id]['sent_back'])->toBeFalse()
        ->and($this->getJson('/api/admin/approval-counts')->json('crawler'))->toBe(2);

    $this->actingAs(User::factory()->create(['type' => 'g']));
    $this->getJson('/api/admin/manage/crawler-events')->assertForbidden();
});

it('flags an organizer that is not approved yet', function () {
    [$crawler] = crawlerSetup();
    heldDraft(Organizer::factory()->create(['status' => 'r']), $crawler);

    $this->actingAs(User::factory()->create(['type' => 'm']));

    expect($this->getJson('/api/admin/manage/crawler-events')->json('data.0.organizer.new'))->toBeTrue();
});

it('lets a moderator open and submit a held draft, which moves to the approval queue still held', function () {
    Notification::fake();
    [$crawler, $organizer] = crawlerSetup();
    $draft = heldDraft($organizer, $crawler);

    $this->actingAs(User::factory()->create(['type' => 'm']));
    $this->get("/hosting/event/{$draft->slug}/edit")->assertOk();
    $this->postJson("/hosting/event/{$draft->slug}/submit")->assertOk();

    expect($draft->fresh()->status)->toBe('r')
        ->and($draft->fresh()->held_for_staff)->toBeTrue()
        ->and($this->getJson('/api/admin/manage/crawler-events')->json('data'))->toBe([])
        ->and(collect($this->getJson('/api/admin/approve/events')->json('data'))->pluck('id')->all())->toContain($draft->id);
});

it('lifts the hold when a moderator approves it from the queue', function () {
    Notification::fake();
    \Illuminate\Support\Facades\Storage::fake('digitalocean');
    [$crawler, $organizer] = crawlerSetup();
    $draft = heldDraft($organizer, $crawler, ['status' => 'r']);

    $this->actingAs(User::factory()->create(['type' => 'm']));
    $this->postJson("/api/admin/approve/events/{$draft->slug}/approve")->assertSuccessful();

    expect($draft->fresh()->held_for_staff)->toBeFalse()
        ->and($draft->fresh()->status)->toBeIn(['p', 'e']);
});
