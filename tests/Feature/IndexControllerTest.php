<?php

use App\Models\Admin\Dock;
use App\Models\Curated\Card;
use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use App\Models\Event;

// IndexController@index (GET /) renders the homepage with the "home" docks,
// eager-loading posts/cards/shelves/communities and ordering docks by `order` asc.

test('homepage renders the index view with a 200', function () {
    $this->get('/')
        ->assertOk()
        ->assertViewIs('index')
        ->assertViewHas('docks');
});

test('homepage only includes docks whose location is home', function () {
    $home = Dock::factory()->create(['location' => 'home', 'order' => 0]);
    Dock::factory()->create(['location' => 'about', 'order' => 0]);
    Dock::factory()->create(['location' => 'footer', 'order' => 0]);

    $response = $this->get('/')->assertOk();

    $docks = $response->viewData('docks');
    expect($docks)->toHaveCount(1);
    expect($docks->first()->id)->toBe($home->id);
});

test('homepage returns no docks when none are located home', function () {
    Dock::factory()->create(['location' => 'about']);

    $response = $this->get('/')->assertOk();

    expect($response->viewData('docks'))->toHaveCount(0);
});

test('homepage orders home docks by order ascending', function () {
    $third = Dock::factory()->create(['location' => 'home', 'order' => 30]);
    $first = Dock::factory()->create(['location' => 'home', 'order' => 10]);
    $second = Dock::factory()->create(['location' => 'home', 'order' => 20]);

    $response = $this->get('/')->assertOk();

    $ids = $response->viewData('docks')->pluck('id')->all();
    expect($ids)->toBe([$first->id, $second->id, $third->id]);
});

test('homepage eager-loads associated published posts and their cards on a home dock', function () {
    $dock = Dock::factory()->create(['location' => 'home', 'order' => 0]);

    // A published post associated to the dock via the polymorphic association pivot.
    $post = Post::factory()->published()->create(['order' => 0]);
    $event = Event::factory()->published()->create();
    Card::factory()->create(['post_id' => $post->id, 'event_id' => $event->id, 'type' => 'e']);
    $dock->posts()->attach($post->id);

    $response = $this->get('/')->assertOk();

    $docks = $response->viewData('docks');
    $loadedDock = $docks->firstWhere('id', $dock->id);

    expect($loadedDock->relationLoaded('posts'))->toBeTrue();
    expect($loadedDock->posts)->toHaveCount(1);
    expect($loadedDock->posts->first()->relationLoaded('cards'))->toBeTrue();
    expect($loadedDock->relationLoaded('cards'))->toBeTrue();
    expect($loadedDock->relationLoaded('shelves'))->toBeTrue();
    expect($loadedDock->relationLoaded('communities'))->toBeTrue();
});

test('homepage limits the dock posts relation to status p', function () {
    $dock = Dock::factory()->create(['location' => 'home', 'order' => 0]);

    $published = Post::factory()->published()->create(['order' => 0]);
    $draft = Post::factory()->draft()->create(['order' => 1]);
    $dock->posts()->attach([$published->id, $draft->id]);

    $response = $this->get('/')->assertOk();

    $loadedDock = $response->viewData('docks')->firstWhere('id', $dock->id);

    // The posts closure filters on status === 'p', so the draft post is excluded
    // even though it is attached to the dock.
    expect($loadedDock->posts->pluck('id')->all())->toBe([$published->id]);
});

test('homepage cards relation on a dock is eager-loaded and limited', function () {
    $dock = Dock::factory()->create(['location' => 'home', 'order' => 0]);

    $post = Post::factory()->published()->create();
    $event = Event::factory()->published()->create();
    $card = Card::factory()->create(['post_id' => $post->id, 'event_id' => $event->id, 'type' => 'e']);
    $dock->cards()->attach($card->id);

    $response = $this->get('/')->assertOk();

    $loadedDock = $response->viewData('docks')->firstWhere('id', $dock->id);
    expect($loadedDock->cards)->toHaveCount(1);
    expect($loadedDock->cards->first()->id)->toBe($card->id);
});

test('homepage only loads non-hidden shelves on a dock', function () {
    $dock = Dock::factory()->create(['location' => 'home', 'order' => 0]);

    $visible = Shelf::factory()->create(['is_hidden' => false]);
    $hidden = Shelf::factory()->hidden()->create();
    $dock->shelves()->attach([$visible->id, $hidden->id]);

    $response = $this->get('/')->assertOk();

    $loadedDock = $response->viewData('docks')->firstWhere('id', $dock->id);

    // The shelves closure filters is_hidden === false.
    expect($loadedDock->shelves->pluck('id')->all())->toBe([$visible->id]);
});
