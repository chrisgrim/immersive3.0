<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\StoreCommunityRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\StoreOrganizerRequest;
use App\Http\Requests\StoreProfileRequest;
use App\Models\Curated\Community;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

// These tests exercise the rules() methods of the form requests directly.
// Several requests build their rules conditionally on request state
// ($this->has(), $this->hasFile(), $this->user(), $this->route()), so we
// resolve a real request instance seeded with data/files/user before calling
// rules(), then run a fresh Validator over the same payload.

/**
 * Build a FormRequest instance populated with $data (and optional $files),
 * bound to the container, with an optional authenticated user and route
 * parameters, so conditional rule logic resolves correctly.
 */
function makeRequest(string $class, array $data = [], array $files = [], ?User $user = null, array $routeParams = [])
{
    /** @var \Illuminate\Foundation\Http\FormRequest $request */
    $request = $class::create('/', 'POST', $data, [], $files);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    if ($user) {
        $request->setUserResolver(fn () => $user);
    }

    if ($routeParams) {
        $route = new Illuminate\Routing\Route('POST', '/', []);
        $route->bind($request); // initialise the parameters bag
        foreach ($routeParams as $key => $value) {
            $route->setParameter($key, $value);
        }
        $request->setRouteResolver(fn () => $route);
    }

    return $request;
}

/**
 * Run the request's own rules() against the supplied data and return the
 * validator (already evaluated).
 */
function validateWith($request, array $data)
{
    return Validator::make($data, $request->rules());
}

// =====================================================================
// StoreCommunityRequest
// =====================================================================

test('StoreCommunity passes with a valid name, blurb and description', function () {
    $data = [
        'name' => 'Immersive Theatre Fans',
        'blurb' => 'A place for fans of immersive theatre.',
        'description' => 'We discuss and curate immersive theatre experiences worldwide.',
    ];
    $request = makeRequest(StoreCommunityRequest::class, $data);

    expect(validateWith($request, $data)->passes())->toBeTrue();
});

test('StoreCommunity fails when name exceeds 60 characters', function () {
    $data = [
        'name' => str_repeat('a', 61),
        'blurb' => 'short blurb',
    ];
    $request = makeRequest(StoreCommunityRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('StoreCommunity fails when blurb exceeds 160 characters', function () {
    $data = [
        'name' => 'Valid Name',
        'blurb' => str_repeat('b', 161),
    ];
    $request = makeRequest(StoreCommunityRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('blurb'))->toBeTrue();
});

test('StoreCommunity requires blurb whenever name is present', function () {
    // note: blurb becomes `required` purely because `name` is present in the
    // request body — there is no separate "blurb supplied" check.
    $data = ['name' => 'Valid Name'];
    $request = makeRequest(StoreCommunityRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('blurb'))->toBeTrue();
});

test('StoreCommunity fails when description exceeds 2000 characters', function () {
    $data = ['description' => str_repeat('c', 2001)];
    $request = makeRequest(StoreCommunityRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('description'))->toBeTrue();
});

test('StoreCommunity ignores name/blurb rules entirely when name is absent', function () {
    // note: rules are gated on $this->has('name'); with no name in the body
    // the rule set is empty for those fields, so an empty payload passes.
    $request = makeRequest(StoreCommunityRequest::class, []);

    expect(validateWith($request, [])->passes())->toBeTrue();
});

test('StoreCommunity UniqueSlugRule rejects a name that slugs to an existing community', function () {
    $existing = Community::factory()->create(['name' => 'Taken Name']);

    $data = [
        'name' => 'Taken Name',
        'blurb' => 'a blurb',
    ];
    $request = makeRequest(StoreCommunityRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('StoreCommunity UniqueSlugRule ignores the community being updated', function () {
    $existing = Community::factory()->create(['name' => 'Taken Name']);

    $data = [
        'name' => 'Taken Name',
        'blurb' => 'a blurb',
    ];
    // Pass the same community as the route param so its id is excluded.
    $request = makeRequest(StoreCommunityRequest::class, $data, [], null, ['community' => $existing]);

    expect(validateWith($request, $data)->passes())->toBeTrue();
});

// =====================================================================
// StoreOrganizerRequest
// =====================================================================

test('StoreOrganizer passes with a fully valid payload', function () {
    $data = [
        'name' => 'Punchdrunk',
        'description' => 'An immersive theatre company.',
        'email' => 'hello@example.com',
        'website' => 'https://example.com',
        'instagramHandle' => 'punchdrunk',
        'twitterHandle' => 'punchdrunk',
        'facebookHandle' => 'punchdrunk',
        'patreon' => 'punchdrunk',
    ];
    $request = makeRequest(StoreOrganizerRequest::class, $data);

    expect(validateWith($request, $data)->passes())->toBeTrue();
});

test('StoreOrganizer website must start with https:// (regex)', function () {
    $data = [
        'website' => 'http://example.com',
    ];
    $request = makeRequest(StoreOrganizerRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('website'))->toBeTrue();
});

test('StoreOrganizer accepts an https website', function () {
    $data = ['website' => 'https://example.com'];
    $request = makeRequest(StoreOrganizerRequest::class, $data);

    expect(validateWith($request, $data)->passes())->toBeTrue();
});

test('StoreOrganizer rejects an invalid email', function () {
    $data = ['email' => 'not-an-email'];
    $request = makeRequest(StoreOrganizerRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('email'))->toBeTrue();
});

test('StoreOrganizer name is required when the name key is present', function () {
    // note: name rule is only added when $this->has('name'); once present it
    // is `required`, so an empty-string name fails.
    $data = ['name' => ''];
    $request = makeRequest(StoreOrganizerRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('StoreOrganizer name has an 80 character cap', function () {
    $data = ['name' => str_repeat('n', 81)];
    $request = makeRequest(StoreOrganizerRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('StoreOrganizer description has a 2000 character cap', function () {
    $data = ['description' => str_repeat('d', 2001)];
    $request = makeRequest(StoreOrganizerRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('description'))->toBeTrue();
});

test('StoreOrganizer enforces handle max lengths', function () {
    $data = [
        'instagramHandle' => str_repeat('i', 31), // max 30
        'twitterHandle' => str_repeat('t', 16),    // max 15
        'facebookHandle' => str_repeat('f', 51),   // max 50
        'patreon' => str_repeat('p', 31),          // max 30
    ];
    $request = makeRequest(StoreOrganizerRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('instagramHandle'))->toBeTrue();
    expect($validator->errors()->has('twitterHandle'))->toBeTrue();
    expect($validator->errors()->has('facebookHandle'))->toBeTrue();
    expect($validator->errors()->has('patreon'))->toBeTrue();
});

test('StoreOrganizer description rule is absent when description key is missing', function () {
    // Social/contact fields are always present in the rule set but all nullable,
    // so an empty payload passes.
    $request = makeRequest(StoreOrganizerRequest::class, []);

    expect(validateWith($request, [])->passes())->toBeTrue();
});

test('StoreOrganizer image rule only applies when a file is uploaded', function () {
    $file = UploadedFile::fake()->create('logo.txt', 10, 'text/plain');
    $data = [];
    $request = makeRequest(StoreOrganizerRequest::class, $data, ['image' => $file]);

    // The request now includes the image rule (required|image|mimes:...).
    $validator = Validator::make(['image' => $file], $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('image'))->toBeTrue();
});

// =====================================================================
// StoreProfileRequest
// =====================================================================

test('StoreProfile passes with a valid payload', function () {
    $user = User::factory()->create();
    $data = [
        'name' => 'New Name',
        'email' => 'fresh@example.com',
        'newsletter_type' => 'm',
        'silence' => 'n',
    ];
    $request = makeRequest(StoreProfileRequest::class, $data, [], $user);

    expect(validateWith($request, $data)->passes())->toBeTrue();
});

test('StoreProfile rejects a newsletter_type outside a/m/u/n', function () {
    $user = User::factory()->create();
    $data = ['newsletter_type' => 'x'];
    $request = makeRequest(StoreProfileRequest::class, $data, [], $user);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('newsletter_type'))->toBeTrue();
});

test('StoreProfile accepts each allowed newsletter_type value', function () {
    $user = User::factory()->create();
    foreach (['a', 'm', 'u', 'n'] as $value) {
        $data = ['newsletter_type' => $value];
        $request = makeRequest(StoreProfileRequest::class, $data, [], $user);
        expect(validateWith($request, $data)->passes())->toBeTrue();
    }
});

test('StoreProfile rejects a silence value outside y/n', function () {
    $user = User::factory()->create();
    $data = ['silence' => 'maybe'];
    $request = makeRequest(StoreProfileRequest::class, $data, [], $user);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('silence'))->toBeTrue();
});

test('StoreProfile rejects an email already taken by another user', function () {
    $other = User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $data = ['email' => 'taken@example.com'];
    $request = makeRequest(StoreProfileRequest::class, $data, [], $user);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('email'))->toBeTrue();
});

test('StoreProfile allows the user to keep their own email (unique ignores self)', function () {
    // note: unique rule is `unique:users,email,{currentUserId}` so the
    // authenticated user submitting their own email is not flagged.
    $user = User::factory()->create(['email' => 'mine@example.com']);

    $data = ['email' => 'mine@example.com'];
    $request = makeRequest(StoreProfileRequest::class, $data, [], $user);

    expect(validateWith($request, $data)->passes())->toBeTrue();
});

test('StoreProfile rejects a malformed email', function () {
    $user = User::factory()->create();
    $data = ['email' => 'not-an-email'];
    $request = makeRequest(StoreProfileRequest::class, $data, [], $user);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('email'))->toBeTrue();
});

test('StoreProfile rejects an image with a disallowed mime type', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('avatar.gif', 100, 'image/gif');
    $data = ['image' => $file];
    $request = makeRequest(StoreProfileRequest::class, $data, ['image' => $file], $user);

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('image'))->toBeTrue();
});

test('StoreProfile rejects an image larger than 5MB', function () {
    $user = User::factory()->create();
    // 5MB max == 5120 KB; create a 6000 KB png.
    $file = UploadedFile::fake()->create('avatar.png', 6000, 'image/png');
    $data = ['image' => $file];
    $request = makeRequest(StoreProfileRequest::class, $data, ['image' => $file], $user);

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('image'))->toBeTrue();
});

test('StoreProfile accepts a valid png image under the size cap', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('avatar.png', 100, 'image/png');
    $data = ['image' => $file];
    $request = makeRequest(StoreProfileRequest::class, $data, ['image' => $file], $user);

    expect(Validator::make($data, $request->rules())->passes())->toBeTrue();
});

// =====================================================================
// StoreEventRequest (cover the most important required/enum/length rules)
// =====================================================================

test('StoreEvent passes with a minimal valid payload', function () {
    $data = [
        'name' => 'My Immersive Show',
        'description' => 'A wonderful experience.',
        'showtype' => 'a',
    ];
    $request = makeRequest(StoreEventRequest::class, $data);

    expect(validateWith($request, $data)->passes())->toBeTrue();
});

test('StoreEvent name has a 100 character cap', function () {
    $data = ['name' => str_repeat('n', 101)];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('StoreEvent description has a 5000 character cap', function () {
    $data = ['description' => str_repeat('d', 5001)];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('description'))->toBeTrue();
});

test('StoreEvent showtype only accepts s, a or o', function () {
    $data = ['showtype' => 'l']; // 'l' (limited) is intentionally excluded here
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('showtype'))->toBeTrue();
});

test('StoreEvent accepts each allowed showtype value', function () {
    foreach (['s', 'a', 'o'] as $value) {
        // showtype 's' requires dateArray, so supply one when needed.
        $data = ['showtype' => $value];
        if ($value === 's') {
            $data['dateArray'] = [now()->format('Y-m-d H:i:s')];
        }
        $request = makeRequest(StoreEventRequest::class, $data);
        expect(validateWith($request, $data)->passes())->toBeTrue();
    }
});

test('StoreEvent dateArray is required when showtype is s', function () {
    $data = ['showtype' => 's'];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('dateArray'))->toBeTrue();
});

test('StoreEvent dateArray entries must match Y-m-d H:i:s', function () {
    $data = [
        'showtype' => 's',
        'dateArray' => ['2026-05-28'], // missing time portion
    ];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('dateArray.0'))->toBeTrue();
});

test('StoreEvent status only accepts the allow-listed values', function () {
    // note: 'p','e','r','n' are deliberately excluded — those transitions go
    // through the submit/approve/reject endpoints, not this request.
    $data = ['status' => 'p'];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('status'))->toBeTrue();
});

test('StoreEvent accepts a draft status', function () {
    $data = ['status' => 'd'];
    $request = makeRequest(StoreEventRequest::class, $data);

    expect(validateWith($request, $data)->passes())->toBeTrue();
});

test('StoreEvent websiteUrl must be a valid url', function () {
    $data = ['websiteUrl' => 'not a url'];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('websiteUrl'))->toBeTrue();
});

test('StoreEvent embargo_date must be in the future', function () {
    $data = ['embargo_date' => now()->subDay()->format('Y-m-d H:i:s')];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('embargo_date'))->toBeTrue();
});

test('StoreEvent limits genres to 10 and requires a genre name', function () {
    $genres = [];
    for ($i = 0; $i < 11; $i++) {
        $genres[] = ['id' => $i, 'name' => 'Genre '.$i];
    }
    $data = ['genres' => $genres];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('genres'))->toBeTrue();
});

test('StoreEvent caps content advisories at 16', function () {
    $advisories = [];
    for ($i = 0; $i < 17; $i++) {
        $advisories[] = ['name' => 'Advisory '.$i];
    }
    $data = ['contentAdvisories' => $advisories];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('contentAdvisories'))->toBeTrue();
});

test('StoreEvent ticket name is required with tickets and capped at 40', function () {
    $data = [
        'tickets' => [
            ['name' => str_repeat('t', 41), 'ticket_price' => 10, 'currency' => 'USD'],
        ],
    ];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tickets.0.name'))->toBeTrue();
});

test('StoreEvent rejects a negative ticket price', function () {
    $data = [
        'tickets' => [
            ['name' => 'GA', 'ticket_price' => -5, 'currency' => 'USD'],
        ],
    ];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tickets.0.ticket_price'))->toBeTrue();
});

test('StoreEvent requires sexualDescription when advisories.sexual is true', function () {
    $data = [
        'advisories' => [
            'sexual' => true,
        ],
    ];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('advisories.sexualDescription'))->toBeTrue();
});

test('StoreEvent ongoing_config daysOfWeek values must be between 0 and 6', function () {
    $data = [
        'ongoing_config' => [
            'daysOfWeek' => [7], // out of range
        ],
    ];
    $request = makeRequest(StoreEventRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('ongoing_config.daysOfWeek.0'))->toBeTrue();
});

// =====================================================================
// LoginRequest
// =====================================================================

test('Login passes with a valid email and password', function () {
    $data = ['email' => 'user@example.com', 'password' => 'secret'];
    $request = makeRequest(LoginRequest::class, $data);

    expect(validateWith($request, $data)->passes())->toBeTrue();
});

test('Login requires the email field', function () {
    $data = ['password' => 'secret'];
    $request = makeRequest(LoginRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('email'))->toBeTrue();
});

test('Login rejects a malformed email', function () {
    $data = ['email' => 'nope', 'password' => 'secret'];
    $request = makeRequest(LoginRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('email'))->toBeTrue();
});

test('Login requires the password field', function () {
    $data = ['email' => 'user@example.com'];
    $request = makeRequest(LoginRequest::class, $data);

    $validator = validateWith($request, $data);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('password'))->toBeTrue();
});
