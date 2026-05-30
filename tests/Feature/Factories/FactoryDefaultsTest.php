<?php

use App\Models\Curated\Post;
use App\Models\Event;
use App\Models\Image;

// Regression tests for two factory-default bugs surfaced in the pre-deploy review:
//   X5 — PostFactory minted the post's shelf in its OWN community, leaving the post's
//        shelf in a different community than the post (an impossible state in the app).
//   X6 — ImageFactory left the NOT NULL polymorphic columns null, so a bare create() threw.

test('PostFactory puts the post and its shelf in the same community', function () {
    $post = Post::factory()->create();

    expect($post->shelf)->not->toBeNull();
    expect($post->shelf->community_id)->toBe($post->community_id);
});

test('ImageFactory bare create() attaches to a real polymorphic parent', function () {
    $image = Image::factory()->create();

    expect($image->imageable_id)->not->toBeNull();
    expect($image->imageable_type)->toBe(Event::class);
    expect($image->imageable)->not->toBeNull(); // morphTo resolves to a real model
});

test('ImageFactory still attaches to a given parent via ->for() without a throwaway Event', function () {
    $event = Event::factory()->create();

    $image = Image::factory()->for($event, 'imageable')->create();

    expect($image->imageable_id)->toBe($event->id);
    expect($image->imageable_type)->toBe(Event::class);
    // The ->for() FK overrides the default before it resolves, so only the one event exists.
    expect(Event::count())->toBe(1);
});
