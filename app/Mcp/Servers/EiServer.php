<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AttachEventImage;
use App\Mcp\Tools\CreateEventDraft;
use App\Mcp\Tools\CreateOrganizer;
use App\Mcp\Tools\GetEvent;
use App\Mcp\Tools\ListEventAttributes;
use App\Mcp\Tools\ListMyEvents;
use App\Mcp\Tools\SubmitEventForReview;
use App\Mcp\Tools\UpdateEvent;
use App\Mcp\Tools\Whoami;
use Laravel\Mcp\Server;

class EiServer extends Server
{
    protected string $name = 'Everything Immersive';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
    Everything Immersive is an event discovery platform for immersive experiences
    (immersive theatre, escape rooms, VR, interactive art, and similar).

    You act on behalf of the authenticated user. Typical workflow:

    1. `whoami` — see who you are and which organizers (teams) you belong to.
    2. If the user has no organizer, create one with `create-organizer` (it goes to
       admin review, but you can create event drafts under it immediately).
    3. `create-event-draft` — creates an empty draft under an organizer.
    4. `update-event` — fill in fields incrementally (partial updates). Use
       `list-event-attributes` to discover valid category/genre/advisory IDs.
       Set the schedule (`showtype` + dates) before or together with `tickets`.
       All dates are UTC in `Y-m-d H:i:s` format.
    5. `get-event` — check the readiness checklist for anything missing.
    6. `submit-event-for-review` — sends the event to human admin review.

    Events only go live after a human admin approves them; there is no way to
    publish directly. Ask the user before acknowledging duplicate-name warnings.
    MARKDOWN;

    protected array $tools = [
        Whoami::class,
        ListEventAttributes::class,
        ListMyEvents::class,
        GetEvent::class,
        CreateOrganizer::class,
        CreateEventDraft::class,
        UpdateEvent::class,
        AttachEventImage::class,
        SubmitEventForReview::class,
    ];
}
