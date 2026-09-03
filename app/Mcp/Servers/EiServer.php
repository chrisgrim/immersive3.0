<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AttachEventImage;
use App\Mcp\Tools\CreateEventDraft;
use App\Mcp\Tools\CreateOrganizer;
use App\Mcp\Tools\GeocodeAddress;
use App\Mcp\Tools\GetEvent;
use App\Mcp\Tools\ListAllEvents;
use App\Mcp\Tools\ListEventAttributes;
use App\Mcp\Tools\ListMyEvents;
use App\Mcp\Tools\SubmitEventForReview;
use App\Mcp\Tools\UpdateEvent;
use App\Mcp\Tools\UpdateOrganizer;
use App\Mcp\Tools\Whoami;
use Laravel\Mcp\Server;

class EiServer extends Server
{
    protected string $name = 'Everything Immersive';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
    Everything Immersive is an event discovery platform for immersive experiences
    (immersive theatre, escape rooms, VR, interactive art, and similar).

    You act on behalf of the authenticated user. Start with `whoami`; if the user
    has no organizer, `create-organizer` first — collect ALL details including
    the optional ones (email, website, social handles, Patreon, logo) BEFORE
    creating: it is submitted for review immediately and cannot be edited again
    until an admin approves or rejects it. The same lock applies to submitted
    events: anything with status "in review" is read-only until reviewed.
    Then `create-event-draft`.

    Filling in an event mirrors the website's step-by-step wizard — walk the user
    through it in this order, asking rather than assuming:

    1. In person or online? (attendance_type_id — decides everything below)
    2. Name + tagline (both required; duplicate names trigger a warning to
       confirm with the user)
    3. Category (fetch valid ones for the attendance type via
       list-event-attributes), then 1-10 genres
    4. In person: resolve the address with `geocode-address` and confirm the
       match with the user — never guess coordinates. Ask if the exact location
       is a secret; if so it needs an explanation of how attendees learn it.
       Online: which platforms (remotelocations) + how to join.
    5. Description (up to 5000 chars)
    6. Schedule: specific dates, ongoing/recurring, or always available —
       then tickets (1-10 tiers), the ticket purchase URL, and button text.
       All datetimes are UTC "Y-m-d H:i:s".
    7. Primary image via `attach-event-image` (rank 0; gallery = ranks 1-4)
    8. Advisories — ask each explicitly: contact level, age limit, interaction
       level, the audience's role, whether there is sexual content (description
       required if yes), at least one content advisory, wheelchair accessibility
       (yes/no), and at least one mobility advisory. Prefer the options from
       list-event-attributes over inventing new ones.

    `get-event` returns a readiness checklist of what's still missing;
    `submit-event-for-review` enforces it and sends the event to human review.

    Finding an existing event: `list-my-events` covers only the organizers the
    user belongs to. `list-all-events` searches the whole platform — use it
    whenever an event might live under someone else's organizer, or to sweep for
    work (e.g. `closing_before` finds runs about to expire). A moderator or
    admin can edit ANY event they find there with `update-event` — but only
    when the credential carries the `mcp:moderate` scope, which an API key
    created with moderator powers has and a connection made through sign-in
    never does. `whoami` reports `is_moderator` for the credential in use; if
    it is false, treat the account like any other organizer's.

    Editing a schedule: send the field that matches the event's showtype —
    `dateArray` for "s", `ongoing_config` for "o", `always_config` for "a".
    Sending the wrong one is rejected rather than silently ignored. `dateArray`
    REPLACES the whole schedule, so include the past dates you want to keep;
    `ongoing_config` regenerates the run from its recipe. Fields you do not send
    are left alone.

    Safety rules:
    - Events only go live after a human admin approves them; you cannot publish.
    - Editing a PUBLISHED event applies immediately: the first update-event call
      returns a current-vs-proposed diff — show it to the user and only retry
      with confirm_live_edit=true after they explicitly approve.
    - Ask the user before acknowledging duplicate-name warnings.
    MARKDOWN;

    protected array $tools = [
        Whoami::class,
        ListEventAttributes::class,
        ListMyEvents::class,
        ListAllEvents::class,
        GetEvent::class,
        GeocodeAddress::class,
        CreateOrganizer::class,
        UpdateOrganizer::class,
        CreateEventDraft::class,
        UpdateEvent::class,
        AttachEventImage::class,
        SubmitEventForReview::class,
    ];
}
