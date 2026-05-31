<?php

use App\Models\Curated\Card;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use App\Models\Event;
use App\Models\Image;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

// ===================================================================
// Curated\Shelf::publishedPosts()
// ===================================================================

test('publishedPosts returns only published, non-hidden posts', function () {
    $shelf = Shelf::factory()->create();

    $published = Post::factory()->create(['shelf_id' => $shelf->id, 'status' => 'p', 'is_hidden' => false]);
    Post::factory()->create(['shelf_id' => $shelf->id, 'status' => 'd', 'is_hidden' => false]); // draft
    Post::factory()->create(['shelf_id' => $shelf->id, 'status' => 'p', 'is_hidden' => true]);  // hidden

    $result = $shelf->publishedPosts()->get();

    expect($result)->toHaveCount(1);
    expect($result->first()->id)->toBe($published->id);
});

test('dockPosts includes hidden posts but still excludes drafts', function () {
    $shelf = Shelf::factory()->create();

    Post::factory()->create(['shelf_id' => $shelf->id, 'status' => 'p', 'is_hidden' => false]);
    Post::factory()->create(['shelf_id' => $shelf->id, 'status' => 'p', 'is_hidden' => true]);  // still shown for docks
    Post::factory()->create(['shelf_id' => $shelf->id, 'status' => 'd', 'is_hidden' => false]); // draft excluded

    expect($shelf->dockPosts()->get())->toHaveCount(2);
});

test('publishedPosts caps results at four', function () {
    $shelf = Shelf::factory()->create();
    Post::factory()->count(6)->create(['shelf_id' => $shelf->id, 'status' => 'p', 'is_hidden' => false]);

    expect($shelf->publishedPosts()->get())->toHaveCount(4);
});

// ===================================================================
// Curated\Post soft deletes + forceDelete cascade
// ===================================================================

test('deleting a post soft-deletes the row rather than removing it', function () {
    $post = Post::factory()->create();

    $post->delete();

    expect(Post::find($post->id))->toBeNull();
    expect(Post::withTrashed()->find($post->id))->not->toBeNull();
    expect($post->fresh()->deleted_at)->not->toBeNull();
});

test('a soft-deleted post can be restored', function () {
    $post = Post::factory()->create();
    $post->delete();

    Post::withTrashed()->find($post->id)->restore();

    expect(Post::find($post->id))->not->toBeNull();
});

test('soft-deleting a post leaves its cards intact', function () {
    $post = Post::factory()->create();
    $card = Card::factory()->create(['post_id' => $post->id]);

    $post->delete();

    // note: the cascade only runs on forceDeleting, so a soft delete keeps cards.
    expect(Card::find($card->id))->not->toBeNull();
});

test('force-deleting a post destroys its cards through destroyCard', function () {
    Storage::fake('digitalocean');

    $post = Post::factory()->create();
    $card = Card::factory()->create(['post_id' => $post->id]);

    $post->forceDelete();

    expect(Post::withTrashed()->find($post->id))->toBeNull();
    expect(Card::find($card->id))->toBeNull();
});

// ===================================================================
// Curated\Card::destroyCard() deletes images via ImageHandler
// ===================================================================

test('destroyCard deletes the card and its image files off the disk', function () {
    Storage::fake('digitalocean');

    $card = Card::factory()->create();

    // ImageHandler::deleteImage requires a "<type>-images/<slug>/<file>" path
    // structure and deletes webp + jpg for both the large and thumb variants.
    $image = Image::factory()->create([
        'imageable_id' => $card->id,
        'imageable_type' => Card::class,
        'large_image_path' => 'card-images/destroy-me/orig.webp',
        'thumb_image_path' => 'card-images/destroy-me/orig-thumb.webp',
        'rank' => 1,
    ]);

    Storage::disk('digitalocean')->put('/public/'.$image->large_image_path, 'large-webp');
    Storage::disk('digitalocean')->put('/public/card-images/destroy-me/orig.jpg', 'large-jpg');
    Storage::disk('digitalocean')->put('/public/'.$image->thumb_image_path, 'thumb-webp');
    Storage::disk('digitalocean')->put('/public/card-images/destroy-me/orig-thumb.jpg', 'thumb-jpg');

    $card->destroyCard($card);

    expect(Card::find($card->id))->toBeNull();
    expect(Image::find($image->id))->toBeNull();
    Storage::disk('digitalocean')->assertMissing('/public/'.$image->large_image_path);
    Storage::disk('digitalocean')->assertMissing('/public/card-images/destroy-me/orig.jpg');
    Storage::disk('digitalocean')->assertMissing('/public/'.$image->thumb_image_path);
});

test('destroyCard deletes a card cleanly when it has no images', function () {
    Storage::fake('digitalocean');

    $card = Card::factory()->create();

    $card->destroyCard($card);

    expect(Card::find($card->id))->toBeNull();
});

// ===================================================================
// Curated\Community slug generation
// ===================================================================

test('a community generates a slug from its name on create', function () {
    $user = User::factory()->create();

    $community = Community::create([
        'user_id' => $user->id,
        'name' => 'My Immersive Group',
        'status' => 'p',
    ]);

    expect($community->slug)->toBe('my-immersive-group');
});

test('changing a community name regenerates the slug on update', function () {
    $community = Community::factory()->create(['name' => 'Original Name']);

    $community->update(['name' => 'Brand New Name']);

    expect($community->fresh()->slug)->toBe('brand-new-name');
});

test('updating a community without changing the name leaves the slug untouched', function () {
    $community = Community::factory()->create(['name' => 'Stable Name']);
    $originalSlug = $community->slug;

    $community->update(['blurb' => 'a different blurb']);

    expect($community->fresh()->slug)->toBe($originalSlug);
});

// ===================================================================
// Messaging\Conversation message ordering relations
// ===================================================================

test('messages relation is ordered ascending by updated_at', function () {
    $conversation = Conversation::factory()->create();

    $older = Message::factory()->create(['conversation_id' => $conversation->id]);
    $newer = Message::factory()->create(['conversation_id' => $conversation->id]);

    // Force a distinct ordering on updated_at.
    $older->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $newer->forceFill(['updated_at' => now()])->saveQuietly();

    $ordered = $conversation->messages()->get();

    expect($ordered->pluck('id')->all())->toBe([$older->id, $newer->id]);
});

test('latestMessages relation is ordered descending by updated_at', function () {
    $conversation = Conversation::factory()->create();

    $older = Message::factory()->create(['conversation_id' => $conversation->id]);
    $newer = Message::factory()->create(['conversation_id' => $conversation->id]);

    $older->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $newer->forceFill(['updated_at' => now()])->saveQuietly();

    $ordered = $conversation->latestMessages()->get();

    expect($ordered->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

test('a conversation is soft-deletable and preserves its row', function () {
    $conversation = Conversation::factory()->create();

    $conversation->delete();

    expect(Conversation::find($conversation->id))->toBeNull();
    expect(Conversation::withTrashed()->find($conversation->id))->not->toBeNull();
});

// ===================================================================
// Messaging\Message::notification()
// ===================================================================

test('notification firstOrCreates a conversation and creates the message', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $owner = User::factory()->create();
    $this->actingAs($admin);

    $event = Event::factory()->create(['user_id' => $owner->id, 'name' => 'Cool Show']);

    $message = Message::notification($event, Message::MESSAGES['APPROVED'], $event->slug);

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->message)->toBe(Message::MESSAGES['APPROVED']);
    expect($message->user_id)->toBe($admin->id);

    // The conversation was created keyed to this event + the two participants.
    $conversation = Conversation::find($message->conversation_id);
    expect($conversation)->not->toBeNull();
    expect($conversation->conversable_type)->toBe(Event::class);
    expect($conversation->conversable_id)->toBe($event->id);
    expect($conversation->user_one)->toBe($admin->id);
    expect($conversation->user_two)->toBe($owner->id);
    expect($conversation->subject)->toBe('Cool Show');
});

test('notification sets the recipient unread flag to m', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $owner = User::factory()->create(['unread' => null]);
    $this->actingAs($admin);

    $event = Event::factory()->create(['user_id' => $owner->id]);

    Message::notification($event, Message::MESSAGES['APPROVED'], $event->slug);

    expect($owner->fresh()->unread)->toBe('m');
});

test('notification reuses an existing conversation on a second call', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $owner = User::factory()->create();
    $this->actingAs($admin);

    $event = Event::factory()->create(['user_id' => $owner->id]);

    $first = Message::notification($event, Message::MESSAGES['APPROVED'], $event->slug);
    $second = Message::notification($event, Message::MESSAGES['REJECTED'], $event->slug);

    // firstOrCreate means both messages land on the same conversation.
    expect($second->conversation_id)->toBe($first->conversation_id);
    expect(Conversation::where('conversable_id', $event->id)->where('conversable_type', Event::class)->count())->toBe(1);
    expect(Message::where('conversation_id', $first->conversation_id)->count())->toBe(2);
});

test('notification does not flip unread when the admin is also the owner', function () {
    $admin = User::factory()->create(['type' => 'a', 'unread' => null]);
    $this->actingAs($admin);

    // Event owned by the acting admin: $model->user_id == auth()->id(), so the
    // unread-update branch is skipped.
    $event = Event::factory()->create(['user_id' => $admin->id]);

    Message::notification($event, Message::MESSAGES['APPROVED'], $event->slug);

    expect($admin->fresh()->unread)->toBeNull();
});
