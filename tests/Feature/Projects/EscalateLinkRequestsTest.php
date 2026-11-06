<?php

use App\Mail\LinkRequestEscalated;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->moderator = staffModerator();
    $this->admin = User::factory()->create(['type' => 'a']);
});

function linkRequestOn(Event $event, array $attributes = []): ProjectLinkRequest
{
    return ProjectLinkRequest::create(array_merge([
        'event_id' => $event->id,
        'project_id' => Project::factory()->create()->id,
    ], $attributes));
}

test('it is scheduled', function () {
    $commands = collect(app(Schedule::class)->events())->map->command->filter();

    expect($commands->contains(fn ($c) => str_contains($c, 'ei:escalate-link-requests')))->toBeTrue();
});

test('after 72 hours it marks the request and mails moderators and admins, once', function () {
    $request = linkRequestOn(publishedListing('Run', 'Paris'), ['submitted_at' => now()->subHours(73)]);

    Artisan::call('ei:escalate-link-requests');

    expect($request->fresh()->escalated_at)->not->toBeNull();
    Mail::assertQueued(LinkRequestEscalated::class, 2);
    Mail::assertQueued(LinkRequestEscalated::class, fn ($mail) => $mail->hasTo($this->moderator->email));
    Mail::assertQueued(LinkRequestEscalated::class, fn ($mail) => $mail->hasTo($this->admin->email));

    Artisan::call('ei:escalate-link-requests');

    Mail::assertQueued(LinkRequestEscalated::class, 2);
});

test('it respects a moderator\'s opt-out', function () {
    $this->admin->update(['notification_preferences' => ['events' => false]]);
    linkRequestOn(publishedListing('Run', 'Paris'), ['submitted_at' => now()->subHours(73)]);

    Artisan::call('ei:escalate-link-requests');

    Mail::assertQueued(LinkRequestEscalated::class, 1);
});

test('the clock is submitted_at, not created_at', function () {
    $this->travelTo(now()->subDays(30));
    $request = linkRequestOn(publishedListing('Run', 'Paris'));
    $this->travelBack();
    $request->update(['submitted_at' => now()->subHours(71)]);

    Artisan::call('ei:escalate-link-requests');

    expect($request->fresh()->escalated_at)->toBeNull();
    Mail::assertNothingQueued();
});

test('an unsubmitted duplicate never escalates, however old', function () {
    $source = publishedListing('Source', 'Paris');
    $this->actingAs($source->organizer->owner);
    $this->travelTo(now()->subDays(30));
    $source->duplicate();
    $this->travelBack();

    Artisan::call('ei:escalate-link-requests');

    expect(ProjectLinkRequest::sole()->submitted_at)->toBeNull()
        ->and(ProjectLinkRequest::sole()->escalated_at)->toBeNull();
});

test('the clock starts when the draft is submitted, and from there it escalates', function () {
    $source = publishedListing('Source', 'Paris');
    $this->actingAs($source->organizer->owner);
    $copy = $source->duplicate();
    $request = ProjectLinkRequest::sole();

    $this->postJson("/hosting/event/{$copy->slug}/link-request/confirm")->assertOk();
    $this->postJson("/hosting/event/{$copy->slug}/submit")->assertOk();

    expect($request->fresh()->submitted_at)->not->toBeNull();

    $this->travel(73)->hours();
    Artisan::call('ei:escalate-link-requests');

    expect($request->fresh()->escalated_at)->not->toBeNull();
});

test('a request made on a live listing starts at once', function () {
    $listing = publishedListing('Listing', 'Paris');
    $team = $listing->organizer->owner;

    $request = app(App\Actions\Projects\RequestLink::class)->handle($listing, publicProject(), $team);

    expect($request->submitted_at)->not->toBeNull();
});

test('a request on a listing sent back to its organizer, or deleted, waits on nobody', function () {
    $rejected = publishedListing('Rejected', 'Paris', ['status' => 'n']);
    $deleted = publishedListing('Deleted', 'Paris');
    linkRequestOn($rejected, ['submitted_at' => now()->subDays(9)]);
    linkRequestOn($deleted, ['submitted_at' => now()->subDays(9)]);
    $deleted->delete();

    Artisan::call('ei:escalate-link-requests');

    Mail::assertNothingQueued();
});

test('resubmitting restarts the clock', function () {
    $listing = publishedListing('Back', 'Paris', ['status' => 'n']);
    $request = linkRequestOn($listing, ['submitted_at' => now()->subDays(9)]);

    $listing->update(['status' => 'r']);

    expect($request->fresh()->submitted_at->gt(now()->subMinute()))->toBeTrue();
});

test('going live does not restart a running clock', function () {
    $listing = publishedListing('Queue', 'Paris', ['status' => 'r']);
    $request = linkRequestOn($listing, ['submitted_at' => now()->subHours(10)]);

    $listing->update(['status' => 'p']);

    expect($request->fresh()->submitted_at->lt(now()->subHours(9)))->toBeTrue();
});

test('a decided request is left alone', function () {
    $request = linkRequestOn(publishedListing('Run', 'Paris'), ['submitted_at' => now()->subDays(9), 'status' => ProjectLinkRequest::REJECTED]);

    Artisan::call('ei:escalate-link-requests');

    expect($request->fresh()->escalated_at)->toBeNull();
});

test('the mail renders', function () {
    $request = linkRequestOn(publishedListing('Run', 'Paris'), ['submitted_at' => now()->subDays(9)]);

    (new LinkRequestEscalated($request))->assertSeeInHtml('Run')->assertSeeInHtml($request->project->name);
});
