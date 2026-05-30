<?php

use App\Models\Curated\Card;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// All card routes live behind ['auth', 'verified'] + can:update,community and use
// ->scopeBindings(), which forces Laravel to verify {card}.post_id == {post}.id when
// resolving the binding. The community/post route keys are slugs (getRouteKeyName).

beforeEach(function () {
    // Curator (a non-owner user attached via the curators pivot) — CommunityPolicy::update
    // allows owners, curators, admins, and moderators. We use a plain curator throughout.
    $this->owner = User::factory()->create(['type' => 'u']);
    $this->community = Community::factory()->create(['user_id' => $this->owner->id]);

    $this->curator = User::factory()->create(['type' => 'u']);
    $this->community->curators()->attach($this->curator->id);

    $this->post = Post::factory()->create(['community_id' => $this->community->id]);
});

/**
 * Build the cards URL for a given community/post (+ optional card).
 */
function cardUrl(Community $community, Post $post, ?Card $card = null, string $suffix = ''): string
{
    $base = "/communities/{$community->slug}/posts/{$post->slug}/cards";
    if ($card) {
        $base .= "/{$card->id}";
    }

    return $base.$suffix;
}

// ----- store() -----

test('store creates a card with all attributes', function () {
    $event = Event::factory()->published()->create();

    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $this->post), [
            'event_id' => $event->id,
            'name' => 'My Card',
            'blurb' => 'A short blurb',
            'url' => 'https://example.com',
            'button_text' => 'Go',
            'type' => 'b',
            'order' => 0,
        ])
        ->assertOk();

    $card = Card::where('post_id', $this->post->id)->first();
    expect($card)->not->toBeNull();
    expect($card->event_id)->toBe($event->id);
    expect($card->name)->toBe('My Card');
    expect($card->blurb)->toBe('A short blurb');
    expect($card->url)->toBe('https://example.com');
    expect($card->button_text)->toBe('Go');
    expect($card->type)->toBe('b');
    expect($card->order)->toBe(0);
});

test('store returns the post with its cards loaded', function () {
    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $this->post), [
            'name' => 'Loaded Card',
            'type' => 'b',
            'order' => 0,
        ])
        ->assertOk()
        // The create action returns $post->load('cards.images', 'user').
        ->assertJsonPath('id', $this->post->id)
        ->assertJsonPath('cards.0.name', 'Loaded Card');
});

test('store defaults order to 0 when no cards exist and no order given', function () {
    // note: when order is omitted, the action falls back to
    // ($post->cards()->exists() ? $post->cards->last()->order + 1 : 0).
    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $this->post), [
            'name' => 'First',
            'type' => 'b',
        ])
        ->assertOk();

    expect(Card::where('post_id', $this->post->id)->first()->order)->toBe(0);
});

test('store appends after the last card when order is omitted', function () {
    Card::factory()->create(['post_id' => $this->post->id, 'order' => 0]);
    Card::factory()->create(['post_id' => $this->post->id, 'order' => 1]);

    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $this->post), [
            'name' => 'Appended',
            'type' => 'b',
        ])
        ->assertOk();

    expect(Card::where('post_id', $this->post->id)->where('name', 'Appended')->first()->order)->toBe(2);
});

test('store shifts existing cards order down when an order is given', function () {
    $a = Card::factory()->create(['post_id' => $this->post->id, 'order' => 0]);
    $b = Card::factory()->create(['post_id' => $this->post->id, 'order' => 1]);
    $c = Card::factory()->create(['post_id' => $this->post->id, 'order' => 2]);

    // Insert a new card at order 1 — existing cards with order >= 1 shift +1.
    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $this->post), [
            'name' => 'Inserted',
            'type' => 'b',
            'order' => 1,
        ])
        ->assertOk();

    expect($a->fresh()->order)->toBe(0); // unchanged (order 0 < 1)
    expect($b->fresh()->order)->toBe(2); // shifted 1 -> 2
    expect($c->fresh()->order)->toBe(3); // shifted 2 -> 3

    $inserted = Card::where('post_id', $this->post->id)->where('name', 'Inserted')->first();
    expect($inserted->order)->toBe(1);
});

test('store saves an 800x500 image via ImageHandler when a file is provided', function () {
    Storage::fake('digitalocean');

    $this->actingAs($this->curator)
        ->post(cardUrl($this->community, $this->post), [
            'name' => 'Image Card',
            'type' => 'b',
            'order' => 0,
            'image' => UploadedFile::fake()->image('card.jpg', 1000, 800),
        ])
        ->assertOk();

    $card = Card::where('post_id', $this->post->id)->first();
    expect($card->images()->count())->toBe(1);

    // ImageHandler writes 4 files (large + thumb, in webp + jpg) under
    // /public/card-images/<slug>/ on the digitalocean disk.
    $image = $card->images()->first();
    Storage::disk('digitalocean')->assertExists("/public/{$image->large_image_path}");
    Storage::disk('digitalocean')->assertExists("/public/{$image->thumb_image_path}");
    expect(count(Storage::disk('digitalocean')->allFiles('/public')))->toBe(4);
});

test('store rejects a non-image file when an image field is present', function () {
    Storage::fake('digitalocean');

    // Image validation now runs BEFORE the card is created. We must send
    // Accept: application/json so the ValidationException renders as a 422 JSON body
    // rather than a redirect-back (post() with files can't use postJson).
    $this->actingAs($this->curator)
        ->post(cardUrl($this->community, $this->post), [
            'name' => 'Bad Image Card',
            'type' => 'b',
            'order' => 0,
            'image' => UploadedFile::fake()->create('notimage.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);

    // No orphaned card is left behind.
    expect(Card::where('post_id', $this->post->id)->where('name', 'Bad Image Card')->exists())->toBeFalse();
});

test('store requires authentication', function () {
    // Web routes (auth middleware) redirect guests to login rather than 401.
    $this->post(cardUrl($this->community, $this->post), [
        'name' => 'Nope',
        'type' => 'b',
    ])->assertRedirect();

    expect(Card::where('post_id', $this->post->id)->exists())->toBeFalse();
});

test('store is denied to a user who is not a curator', function () {
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson(cardUrl($this->community, $this->post), [
            'name' => 'Forbidden',
            'type' => 'b',
        ])
        ->assertStatus(403);

    expect(Card::where('post_id', $this->post->id)->exists())->toBeFalse();
});

test('store allows the community owner even when not attached as a curator', function () {
    // CommunityPolicy::update() now authorizes the owner directly. (The owner is normally
    // auto-enrolled as a curator at creation and can't self-remove, but should retain
    // access even if an admin detaches them from the curators pivot.)
    $this->actingAs($this->owner)
        ->postJson(cardUrl($this->community, $this->post), [
            'name' => 'Owner Card',
            'type' => 'b',
        ])
        ->assertOk();

    expect(Card::where('post_id', $this->post->id)->where('name', 'Owner Card')->exists())->toBeTrue();
});

test('store allows an owner who is also attached as a curator', function () {
    $this->community->curators()->attach($this->owner->id);

    $this->actingAs($this->owner)
        ->postJson(cardUrl($this->community, $this->post), [
            'name' => 'Owner Curator Card',
            'type' => 'b',
        ])
        ->assertOk();

    expect(Card::where('post_id', $this->post->id)->where('name', 'Owner Curator Card')->exists())->toBeTrue();
});

test('store allows a moderator', function () {
    $mod = User::factory()->create(['type' => 'm']);

    $this->actingAs($mod)
        ->postJson(cardUrl($this->community, $this->post), [
            'name' => 'Mod Card',
            'type' => 'b',
        ])
        ->assertOk();

    expect(Card::where('post_id', $this->post->id)->where('name', 'Mod Card')->exists())->toBeTrue();
});

// ----- update() -----

test('update changes allow-listed attributes', function () {
    $event = Event::factory()->published()->create();
    $card = Card::factory()->create([
        'post_id' => $this->post->id,
        'name' => 'Old',
        'type' => 'b',
        'order' => 0,
    ]);

    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $this->post, $card), [
            'name' => 'New Name',
            'blurb' => 'New blurb',
            'url' => 'https://new.example.com',
            'button_text' => 'Click',
            'type' => 'e',
            'order' => 3,
            'event_id' => $event->id,
        ])
        ->assertOk();

    $card->refresh();
    expect($card->name)->toBe('New Name');
    expect($card->blurb)->toBe('New blurb');
    expect($card->url)->toBe('https://new.example.com');
    expect($card->button_text)->toBe('Click');
    expect($card->type)->toBe('e');
    expect($card->order)->toBe(3);
    expect($card->event_id)->toBe($event->id);
});

test('update ignores post_id to prevent IDOR', function () {
    // Second post owned by a DIFFERENT community/owner.
    $otherOwner = User::factory()->create(['type' => 'u']);
    $otherCommunity = Community::factory()->create(['user_id' => $otherOwner->id]);
    $otherPost = Post::factory()->create(['community_id' => $otherCommunity->id]);

    $card = Card::factory()->create(['post_id' => $this->post->id, 'name' => 'Mine', 'type' => 'b']);

    // The allow-list in CardActions::update excludes post_id, so this is dropped.
    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $this->post, $card), [
            'name' => 'Updated',
            'post_id' => $otherPost->id,
        ])
        ->assertOk();

    $card->refresh();
    expect($card->name)->toBe('Updated');
    expect($card->post_id)->toBe($this->post->id); // unchanged — IDOR blocked
});

test('update returns the fresh card with event and images', function () {
    $card = Card::factory()->create(['post_id' => $this->post->id, 'name' => 'X', 'type' => 'b']);

    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $this->post, $card), ['name' => 'Renamed'])
        ->assertOk()
        // update returns $card->fresh()->load('event', 'images')
        ->assertJsonPath('id', $card->id)
        ->assertJsonPath('name', 'Renamed');
});

test('update replaces the image, deleting the previous one', function () {
    Storage::fake('digitalocean');

    $card = Card::factory()->create(['post_id' => $this->post->id, 'name' => 'Img', 'type' => 'b']);

    // First upload.
    $this->actingAs($this->curator)
        ->post(cardUrl($this->community, $this->post, $card), [
            'name' => 'Img',
            'image' => UploadedFile::fake()->image('first.jpg', 1000, 800),
        ])
        ->assertOk();

    expect($card->images()->count())->toBe(1);
    $firstImagePath = $card->images()->first()->large_image_path;

    // Second upload replaces the first.
    $this->actingAs($this->curator)
        ->post(cardUrl($this->community, $this->post, $card), [
            'name' => 'Img',
            'image' => UploadedFile::fake()->image('second.jpg', 1000, 800),
        ])
        ->assertOk();

    $card->refresh();
    expect($card->images()->count())->toBe(1); // still just one image
    expect($card->images()->first()->large_image_path)->not->toBe($firstImagePath);
});

test('update is denied to a non-curator', function () {
    $stranger = User::factory()->create(['type' => 'u']);
    $card = Card::factory()->create(['post_id' => $this->post->id, 'name' => 'Safe', 'type' => 'b']);

    $this->actingAs($stranger)
        ->postJson(cardUrl($this->community, $this->post, $card), ['name' => 'Hacked'])
        ->assertStatus(403);

    expect($card->fresh()->name)->toBe('Safe');
});

test('update requires authentication', function () {
    $card = Card::factory()->create(['post_id' => $this->post->id, 'name' => 'Safe', 'type' => 'b']);

    $this->post(cardUrl($this->community, $this->post, $card), ['name' => 'Hacked'])
        ->assertRedirect();

    expect($card->fresh()->name)->toBe('Safe');
});

// ----- order() (bulk reorder) -----

test('order bulk reorders cards', function () {
    $a = Card::factory()->create(['post_id' => $this->post->id, 'order' => 0]);
    $b = Card::factory()->create(['post_id' => $this->post->id, 'order' => 1]);
    $c = Card::factory()->create(['post_id' => $this->post->id, 'order' => 2]);

    $this->actingAs($this->curator)
        ->putJson(cardUrl($this->community, $this->post, null, '/order'), [
            ['id' => $a->id, 'order' => 2],
            ['id' => $b->id, 'order' => 0],
            ['id' => $c->id, 'order' => 1],
        ])
        // note: the controller's order() method returns nothing, so the response is an
        // empty 200 body.
        ->assertOk();

    expect($a->fresh()->order)->toBe(2);
    expect($b->fresh()->order)->toBe(0);
    expect($c->fresh()->order)->toBe(1);
});

test('order is denied to a non-curator', function () {
    $stranger = User::factory()->create(['type' => 'u']);
    $a = Card::factory()->create(['post_id' => $this->post->id, 'order' => 0]);

    $this->actingAs($stranger)
        ->putJson(cardUrl($this->community, $this->post, null, '/order'), [
            ['id' => $a->id, 'order' => 5],
        ])
        ->assertStatus(403);

    expect($a->fresh()->order)->toBe(0);
});

// ----- destroy() -----

test('destroy deletes the card and shifts remaining cards up', function () {
    $a = Card::factory()->create(['post_id' => $this->post->id, 'order' => 0]);
    $b = Card::factory()->create(['post_id' => $this->post->id, 'order' => 1]);
    $c = Card::factory()->create(['post_id' => $this->post->id, 'order' => 2]);

    // Delete the middle card (order 1) — cards with order >= 2 shift -1.
    $this->actingAs($this->curator)
        ->deleteJson(cardUrl($this->community, $this->post, $b))
        ->assertOk();

    expect(Card::find($b->id))->toBeNull();
    expect($a->fresh()->order)->toBe(0); // unchanged
    expect($c->fresh()->order)->toBe(1); // shifted 2 -> 1
});

test('destroy returns the post with remaining cards', function () {
    $a = Card::factory()->create(['post_id' => $this->post->id, 'order' => 0, 'name' => 'Keep']);
    $b = Card::factory()->create(['post_id' => $this->post->id, 'order' => 1, 'name' => 'Remove']);

    $this->actingAs($this->curator)
        ->deleteJson(cardUrl($this->community, $this->post, $b))
        ->assertOk()
        // destroy returns $post->load('cards.images', 'user')
        ->assertJsonPath('id', $this->post->id)
        ->assertJsonPath('cards.0.name', 'Keep')
        ->assertJsonCount(1, 'cards');
});

test('destroy is denied to a non-curator', function () {
    $stranger = User::factory()->create(['type' => 'u']);
    $card = Card::factory()->create(['post_id' => $this->post->id, 'type' => 'b']);

    $this->actingAs($stranger)
        ->deleteJson(cardUrl($this->community, $this->post, $card))
        ->assertStatus(403);

    expect(Card::find($card->id))->not->toBeNull();
});

test('destroy requires authentication', function () {
    $card = Card::factory()->create(['post_id' => $this->post->id, 'type' => 'b']);

    $this->delete(cardUrl($this->community, $this->post, $card))
        ->assertRedirect();

    expect(Card::find($card->id))->not->toBeNull();
});

// ----- scopeBindings() IDOR protection -----

test('update of a card via the wrong post 404s (scopeBindings IDOR protection)', function () {
    // Card C belongs to post A; we request it through post B's URL.
    $postB = Post::factory()->create(['community_id' => $this->community->id]);
    $cardC = Card::factory()->create(['post_id' => $this->post->id, 'name' => 'Protected', 'type' => 'b']);

    // scopeBindings requires cardC.post_id == postB.id, which is false -> 404.
    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $postB, $cardC), ['name' => 'Hacked'])
        ->assertNotFound();

    expect($cardC->fresh()->name)->toBe('Protected'); // untouched
});

test('destroy of a card via the wrong post 404s (scopeBindings IDOR protection)', function () {
    $postB = Post::factory()->create(['community_id' => $this->community->id]);
    $cardC = Card::factory()->create(['post_id' => $this->post->id, 'type' => 'b']);

    $this->actingAs($this->curator)
        ->deleteJson(cardUrl($this->community, $postB, $cardC))
        ->assertNotFound();

    expect(Card::find($cardC->id))->not->toBeNull(); // not deleted
});

test('show via the wrong post 404s even across communities (scopeBindings IDOR protection)', function () {
    // The classic IDOR: a curator of community X trying to edit a card living in
    // community Y's post by pointing the URL at their own post.
    $otherOwner = User::factory()->create(['type' => 'u']);
    $otherCommunity = Community::factory()->create(['user_id' => $otherOwner->id]);
    $otherPost = Post::factory()->create(['community_id' => $otherCommunity->id]);
    $foreignCard = Card::factory()->create(['post_id' => $otherPost->id, 'name' => 'Foreign', 'type' => 'b']);

    // Curator is authorized on $this->community but the card lives elsewhere; the
    // scoped binding (card.post_id == this->post.id) fails first -> 404.
    $this->actingAs($this->curator)
        ->postJson(cardUrl($this->community, $this->post, $foreignCard), ['name' => 'Stolen'])
        ->assertNotFound();

    expect($foreignCard->fresh()->name)->toBe('Foreign');
});
