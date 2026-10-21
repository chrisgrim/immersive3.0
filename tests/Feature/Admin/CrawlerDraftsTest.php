<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * The admin's "AI crawler" section: events Worker drafted as its own account,
 * waiting for a moderator to check, fix and submit through the normal flow.
 */
function crawlerSetup(): array
{
    $crawler = User::factory()->create(['type' => 'm']);
    config(['ei.crawler_user_id' => $crawler->id]);
    $organizer = Organizer::factory()->create(['status' => 'p']);

    return [$crawler, $organizer];
}

it('lists only the crawler account\'s drafts and sent-back events, to moderators', function () {
    [$crawler, $organizer] = crawlerSetup();
    $draft = Event::factory()->create(['user_id' => $crawler->id, 'organizer_id' => $organizer->id, 'status' => '0']);
    $sentBack = Event::factory()->create(['user_id' => $crawler->id, 'organizer_id' => $organizer->id, 'status' => 'n']);
    foreach (['r', 'p', 'e'] as $status) {
        Event::factory()->create(['user_id' => $crawler->id, 'organizer_id' => $organizer->id, 'status' => $status]);
    }
    Event::factory()->create(['organizer_id' => $organizer->id, 'status' => '0']); // someone else's draft
    Event::factory()->create(['user_id' => $crawler->id, 'organizer_id' => $organizer->id, 'status' => '0'])->delete();

    $this->actingAs(User::factory()->create(['type' => 'm']));
    $rows = collect($this->getJson('/api/admin/manage/crawler-events')->assertOk()->json('data'))->keyBy('id');

    expect($rows->keys()->sort()->values()->all())->toBe(collect([$draft->id, $sentBack->id])->sort()->values()->all())
        ->and($rows[$sentBack->id]['sent_back'])->toBeTrue()
        ->and($rows[$draft->id]['sent_back'])->toBeFalse()
        ->and($this->getJson('/api/admin/approval-counts')->json('crawler'))->toBe(2);

    $this->actingAs(User::factory()->create(['type' => 'g']));
    $this->getJson('/api/admin/manage/crawler-events')->assertForbidden();
});

it('is empty when no crawler account is set', function () {
    [$crawler, $organizer] = crawlerSetup();
    Event::factory()->create(['user_id' => $crawler->id, 'organizer_id' => $organizer->id, 'status' => '0']);
    config(['ei.crawler_user_id' => null]);

    $this->actingAs(User::factory()->create(['type' => 'm']));

    expect($this->getJson('/api/admin/manage/crawler-events')->json('data'))->toBe([]);
});

it('flags an organizer that is not approved yet', function () {
    [$crawler] = crawlerSetup();
    $new = Organizer::factory()->create(['status' => 'r']);
    Event::factory()->create(['user_id' => $crawler->id, 'organizer_id' => $new->id, 'status' => '0']);

    $this->actingAs(User::factory()->create(['type' => 'm']));

    expect($this->getJson('/api/admin/manage/crawler-events')->json('data.0.organizer.new'))->toBeTrue();
});

it('lets a moderator open and submit the crawler\'s draft, which then leaves the list for the approval queue', function () {
    Notification::fake();
    [$crawler, $organizer] = crawlerSetup();
    $draft = Event::factory()->create(['user_id' => $crawler->id, 'organizer_id' => $organizer->id, 'status' => '0']);

    $this->actingAs(User::factory()->create(['type' => 'm']));
    $this->get("/hosting/event/{$draft->slug}/edit")->assertOk();
    $this->postJson("/hosting/event/{$draft->slug}/submit")->assertOk();

    expect($draft->fresh()->status)->toBe('r')
        ->and($this->getJson('/api/admin/manage/crawler-events')->json('data'))->toBe([])
        ->and(collect($this->getJson('/api/admin/approve/events')->json('data'))->pluck('id')->all())->toContain($draft->id);
});
