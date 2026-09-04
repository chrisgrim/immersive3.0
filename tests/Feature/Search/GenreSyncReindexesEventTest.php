<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\UpdateEvent;
use App\Models\Event;
use App\Models\Genre;
use App\Models\Organizer;
use App\Models\User;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;

/**
 * Kathryn tagged a season's worth of events "Spooky Season" and the tag
 * search found 11 of 46. UpdateEventAction saves (and so indexes) the
 * event first and syncs the genre pivot afterwards, and nothing re-indexed
 * — so the search index kept every event's tags from before the edit
 * until some unrelated save happened to refresh it.
 *
 * Scout runs on the null driver in tests, so an engine spy records what
 * would have been sent to Elasticsearch.
 */
test('changing an event\'s genres re-indexes it with the new genres', function () {
    $spy = new class extends NullEngine
    {
        public array $updates = [];

        public function update($models)
        {
            $this->updates[] = $models->map(fn ($model) => $model->toSearchableArray())->all();
        }
    };
    $manager = new class($this->app, $spy) extends EngineManager
    {
        public function __construct($app, public $spy)
        {
            parent::__construct($app);
        }

        public function engine($name = null)
        {
            return $this->spy;
        }
    };
    $this->app->instance(EngineManager::class, $manager);

    $user = User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $user->id, 'status' => 'p']);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);
    $old = Genre::factory()->create(['name' => 'Immersive theatre', 'slug' => 'immersive-theatre', 'admin' => true]);
    $event->genres()->sync([$old->id]);
    $spy->updates = [];

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'genres' => [['name' => 'Immersive theatre'], ['name' => 'Spooky Season']],
        'confirm_live_edit' => true,
    ])->assertOk();

    $season = Genre::where('slug', 'spooky-season')->first();
    expect($season)->not->toBeNull();
    expect($event->fresh()->genres()->pluck('genres.id')->all())->toContain($season->id);

    // What the index was last told about this event must include the new tag.
    expect($spy->updates)->not->toBeEmpty();
    $last = collect(end($spy->updates))->first();
    $indexed = collect($last['genres'])->map(fn ($g) => is_array($g) ? ($g['genre_id'] ?? $g['pivot']['genre_id'] ?? null) : ($g->genre_id ?? null))->all();
    expect($indexed)->toContain($season->id);
});
