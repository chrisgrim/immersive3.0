<?php

namespace App\Mcp\Tools;

use App\Actions\Events\CheckDuplicateEventNames;
use App\Actions\Events\UpdateEventAction;
use App\Mcp\Tools\Concerns\BuildsSyntheticRequests;
use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Scopes\PublishedScope;
use App\Support\Validation\EventUpdateRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Partially update one of your event drafts: send only the fields you are changing. Uses the same validation as the website. All dates are UTC "Y-m-d H:i:s". Set showtype + dates before or together with tickets. Publishing is impossible from here — use submit-event-for-review when the draft is complete.')]
class UpdateEvent extends Tool
{
    use BuildsSyntheticRequests;
    use FormatsEvents;

    /**
     * Input keys the MCP surface never accepts: status transitions have
     * dedicated flows (submit/approve) and file uploads have attach tools.
     */
    protected const STRIPPED_KEYS = [
        'status', 'images', 'ranks', 'currentImages', 'deletedImages', 'archived', 'closingDate', 'published_at', 'rank', 'slug',
    ];

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $input = collect($request->all())
            ->except(array_merge(self::STRIPPED_KEYS, ['event_slug', 'acknowledge_duplicate']))
            ->all();

        // The videos array is accepted as a real array here; the shared action
        // expects the web wizard's JSON-encoded string.
        if (isset($input['videos']) && is_array($input['videos'])) {
            $input['videos'] = json_encode($input['videos']);
        }

        $slugValidated = $request->validate(['event_slug' => 'required|string']);

        $event = Event::withoutGlobalScope(PublishedScope::class)
            ->where('slug', $slugValidated['event_slug'])
            ->first();

        if (! $event) {
            return Response::error('No event found with that slug.');
        }

        if (! $user->can('manage', $event)) {
            return Response::error('You do not have permission to edit this event.');
        }

        $validator = Validator::make(
            $input,
            collect(EventUpdateRules::rules())->except(self::STRIPPED_KEYS)->all(),
            EventUpdateRules::messages(),
            EventUpdateRules::attributes()
        );

        if ($validator->fails()) {
            return Response::json([
                'error' => 'validation_failed',
                'errors' => $validator->errors(),
            ]);
        }

        $validated = $validator->validated();

        if (empty($validated)) {
            return Response::error('No updatable fields were provided.');
        }

        // Duplicate-name guard, same as the web flow.
        if (isset($validated['name']) && $validated['name'] !== $event->name && ! $request->get('acknowledge_duplicate')) {
            $duplicates = app(CheckDuplicateEventNames::class)->handle($validated['name'], $event->id);
            if ($duplicates) {
                return Response::json([
                    'error' => 'duplicate_name',
                    'message' => 'An event with a similar name already exists. Ask the user how to proceed; to use the name anyway, call this tool again with acknowledge_duplicate=true.',
                    'duplicateEvents' => $duplicates,
                ]);
            }
        }

        // Tickets attach to shows — make the dependency explicit for agents.
        if (isset($validated['tickets']) && ! isset($validated['showtype']) && $event->shows()->doesntExist()) {
            return Response::error('This event has no dates yet. Set showtype and the schedule (dateArray / ongoing_config / always_config) before or together with tickets.');
        }

        // The web wizard computes hasLocation client-side from the attendance
        // type; mirror that so in-person/remote state stays consistent.
        if (isset($validated['attendance_type_id']) && ! isset($validated['hasLocation'])) {
            $validated['hasLocation'] = $validated['attendance_type_id'] == 1;
        }

        // The shared action mirrors the wizard, where `location` always arrives
        // in its own step: when location is present it skips the top-level
        // mass-assign. MCP clients may combine everything in one call, so apply
        // location separately from the other fields.
        $location = $validated['location'] ?? null;
        $rest = collect($validated)->except('location')->all();

        if ($rest !== []) {
            $event = app(UpdateEventAction::class)->handle(
                $event,
                $rest,
                $this->syntheticRequest($rest, $user)
            );
        }

        if ($location !== null) {
            $event = app(UpdateEventAction::class)->handle(
                $event,
                ['location' => $location],
                $this->syntheticRequest(['location' => $location], $user)
            );
        }

        $readiness = $this->readiness($event->load([
            'shows.tickets', 'location', 'advisories', 'contentAdvisories', 'mobilityAdvisories',
            'contactLevels', 'interactive_level', 'category', 'genres', 'remotelocations', 'images', 'organizer',
        ]));

        return Response::json([
            'message' => 'Event updated.',
            'event' => $this->eventSummary($event),
            'updated_fields' => array_keys($validated),
            'readiness' => $readiness,
            'missing' => collect($readiness)->reject(fn ($ok) => $ok)->keys()->values(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()->description('The event slug from create-event-draft or list-my-events.')->required(),
            'name' => $schema->string()->description('Event name, max 100 chars.'),
            'tag_line' => $schema->string()->description('Short tagline, max 255 chars.'),
            'description' => $schema->string()->description('Full plain-text description, max 5000 chars.'),
            'attendance_type_id' => $schema->integer()->description('1 = In Person, 2 = Remote/online. Set this before choosing a category.'),
            'category_id' => $schema->integer()->description('Category id from list-event-attributes (must support the attendance type, or it will be dissociated).'),
            'genres' => $schema->array()->description('Up to 10 genre objects: [{"id": 1, "name": "Horror"}]. Ids from list-event-attributes; a new name without a matching id creates a user genre.'),
            'location' => $schema->object()->description('For in-person events: {venue, home (street number), street, city, region, region_long, country, country_long, postal_code, latitude, longitude, hiddenLocationToggle (bool), hiddenLocation}. Latitude/longitude are required for the event to be mappable.'),
            'remotelocations' => $schema->array()->description('For remote events: [{"name": "Zoom"}].'),
            'remote_description' => $schema->string()->description('For remote events: how attendees join, max 3000 chars.'),
            'timezone' => $schema->string()->description('IANA timezone of the event, e.g. "America/New_York".'),
            'showtype' => $schema->string()->enum(['s', 'o', 'a'])->description('s = specific dates (requires dateArray), o = ongoing/recurring (ongoing_config), a = always available (always_config). Changing this wipes and recreates all shows and tickets.'),
            'dateArray' => $schema->array()->description('For showtype=s (and o): each show datetime in UTC "Y-m-d H:i:s".'),
            'ongoing_config' => $schema->object()->description('For showtype=o: {startDate, endDate (UTC "Y-m-d H:i:s"), daysOfWeek: [0-6, Sunday=0]}.'),
            'always_config' => $schema->object()->description('For showtype=a: {endDate (UTC "Y-m-d H:i:s")} — when the listing should close.'),
            'show_times' => $schema->string()->description('Human-readable showtimes text, max 500 chars, e.g. "Fridays 8pm, Saturdays 6pm & 9pm".'),
            'tickets' => $schema->array()->description('Ticket tiers applied to every show: [{"name": "General", "ticket_price": 25.00, "currency": "$", "description": ""}]. Use ticket_price 0 for free; name "PWYC" for pay-what-you-can. Requires dates to exist.'),
            'ticketUrl' => $schema->string()->description('URL where attendees buy tickets.'),
            'websiteUrl' => $schema->string()->description('Event or organizer website URL.'),
            'call_to_action' => $schema->string()->description('Custom ticket-button text, max 255 chars.'),
            'contactLevel' => $schema->object()->description('{id, name} from list-event-attributes contact_levels — how much performers interact with the audience.'),
            'interactiveLevel' => $schema->object()->description('{id, name, description} from list-event-attributes interactive_levels — how much the audience participates.'),
            'ageLimit' => $schema->object()->description('{id} from list-event-attributes age_limits.'),
            'advisories' => $schema->object()->description('{sexual: bool, sexualDescription (required if sexual=true, max 1000), audience (who is this for, max 1000)}.'),
            'contentAdvisories' => $schema->array()->description('Content warnings, max 16: [{"name": "Loud noises"}]. See list-event-attributes for common ones.'),
            'mobilityAdvisories' => $schema->array()->description('Mobility/accessibility notes, max 16: [{"name": "Stairs required"}].'),
            'wheelchairReady' => $schema->boolean()->description('Whether the event is wheelchair accessible.'),
            'embargo_date' => $schema->string()->description('Optional UTC "Y-m-d H:i:s" in the future: if set, the event stays hidden until this date after approval.'),
            'videos' => $schema->array()->description('[{"platform": "youtube"|"tiktok", "url": "...", "id": "platform video id", "rank": 0}].'),
            'acknowledge_duplicate' => $schema->boolean()->description('Set true only after the user confirms a duplicate-name warning.'),
        ];
    }
}
