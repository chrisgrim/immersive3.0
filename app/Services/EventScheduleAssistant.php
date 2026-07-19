<?php

namespace App\Services;

use App\Mcp\Tools\UpdateEvent;
use App\Models\Event;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request as McpRequest;

/**
 * A focused, admin-only chat assistant for the event-creation wizard's Dates
 * step. It drives Claude through a tool-use loop, executing changes in-process
 * against the existing MCP UpdateEvent tool so every guard rail (validation,
 * live-edit gating, the wizard step-marker sync) is reused. The assistant is
 * pinned to a single event and may only touch SCHEDULE fields.
 */
class EventScheduleAssistant
{
    protected const MODEL = 'claude-opus-4-8';

    protected const MAX_TOKENS = 8000;

    /** Safety cap on tool-call round trips within one turn. */
    protected const MAX_ITERATIONS = 6;

    /** The only fields this assistant is allowed to change. */
    protected const SCHEDULE_FIELDS = [
        'showtype', 'timezone', 'dateArray',
        'ongoing_config', 'always_config', 'show_times', 'embargo_date',
    ];

    /**
     * Run one assistant turn to completion (including any tool calls).
     *
     * @param  array<int, array{role: string, content: string}>  $history  Prior plain-text turns.
     * @return array{reply: string, schedule_changed: bool, schedule: array<string, mixed>, error?: string}
     */
    public function respond(Event $event, Authenticatable $user, array $history, string $userMessage): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (blank($apiKey)) {
            return [
                'reply' => 'The assistant is not configured (missing Anthropic API key).',
                'schedule_changed' => false,
                'schedule' => $this->scheduleSnapshot($event),
                'error' => 'missing_api_key',
            ];
        }

        $messages = $this->buildMessages($history, $userMessage);
        $scheduleChanged = false;

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $data = $this->callAnthropic($apiKey, $event, $messages);

            if ($data === null) {
                return [
                    'reply' => 'Sorry — I could not reach the assistant service just now. Please try again.',
                    'schedule_changed' => $scheduleChanged,
                    'schedule' => $this->scheduleSnapshot($event->refresh()),
                    'error' => 'api_error',
                ];
            }

            // Echo the assistant turn back verbatim (thinking + tool_use blocks
            // must be preserved unchanged when continuing on the same model).
            $messages[] = ['role' => 'assistant', 'content' => $this->normalizeAssistantContent($data['content'])];

            if (($data['stop_reason'] ?? null) !== 'tool_use') {
                return [
                    'reply' => $this->extractText($data['content']),
                    'schedule_changed' => $scheduleChanged,
                    'schedule' => $this->scheduleSnapshot($event->refresh()),
                ];
            }

            $toolResults = [];
            foreach ($data['content'] as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }

                [$text, $isError, $changed] = $this->executeTool(
                    $event, $user, $block['name'], $block['input'] ?? []
                );
                $scheduleChanged = $scheduleChanged || $changed;

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => $text,
                    'is_error' => $isError,
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return [
            'reply' => "I've made several changes but stopped to avoid looping. Check the calendar and let me know what else you need.",
            'schedule_changed' => $scheduleChanged,
            'schedule' => $this->scheduleSnapshot($event->refresh()),
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: mixed}>
     */
    protected function buildMessages(array $history, string $userMessage): array
    {
        $messages = [];

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // The Messages API requires the first turn to be a user turn.
        while (! empty($messages) && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        return $messages;
    }

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array{content: array<int, mixed>, stop_reason: ?string}|null
     */
    protected function callAnthropic(string $apiKey, Event $event, array $messages): ?array
    {
        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'content-type' => 'application/json',
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => self::MODEL,
                    'max_tokens' => self::MAX_TOKENS,
                    'thinking' => ['type' => 'adaptive'],
                    'system' => $this->systemPrompt($event),
                    'tools' => $this->toolDefinitions(),
                    'messages' => $messages,
                ]);
        } catch (\Throwable $e) {
            Log::error('Schedule assistant API call failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::error('Schedule assistant API error', [
                'status' => $response->status(),
                'body' => $response->json('error.message') ?? $response->body(),
            ]);

            return null;
        }

        return [
            'content' => $response->json('content', []),
            'stop_reason' => $response->json('stop_reason'),
        ];
    }

    /**
     * PHP's associative json_decode turns an empty JSON object `{}` into an
     * empty array `[]`. A tool_use block for a no-argument tool (get_schedule)
     * therefore round-trips its `input` to `[]`, and re-encoding it as a JSON
     * array makes the Messages API reject the next request ("input should be an
     * object"). Force any empty tool_use input back to an object.
     *
     * @param  array<int, mixed>  $content
     * @return array<int, mixed>
     */
    protected function normalizeAssistantContent(array $content): array
    {
        foreach ($content as &$block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use'
                && (! isset($block['input']) || $block['input'] === [])) {
                $block['input'] = (object) [];
            }
        }

        return $content;
    }

    /**
     * Execute a tool call in-process against the pinned event.
     *
     * @return array{0: string, 1: bool, 2: bool} [result text, is_error, schedule_changed]
     */
    protected function executeTool(Event $event, Authenticatable $user, string $name, array $input): array
    {
        if ($name === 'get_schedule') {
            return [json_encode($this->scheduleSnapshot($event->refresh())), false, false];
        }

        if ($name === 'update_schedule') {
            // Whitelist to schedule fields and pin the event — Claude can never
            // set the slug or reach any other event or non-schedule field.
            $payload = collect($input)->only(self::SCHEDULE_FIELDS)->all();

            if ($payload === []) {
                return ['No schedule fields were provided to update.', true, false];
            }

            // Control flag (not a saved field): confirm an irreversible replace.
            if (! empty($input['confirm_schedule_replace'])) {
                $payload['confirm_schedule_replace'] = true;
            }

            $payload['event_slug'] = $event->slug;

            $mcpRequest = new McpRequest($payload);
            $mcpResponse = app(UpdateEvent::class)->handle($mcpRequest);

            $text = (string) $mcpResponse->content();
            $isError = $mcpResponse->isError();

            // A validation_failed / duplicate_name / action_required body comes
            // back as non-error JSON; only treat a true change as a change.
            $changed = ! $isError && ! str_contains($text, '"error"') && ! str_contains($text, '"action_required"');

            return [$text, $isError, $changed];
        }

        return ["Unknown tool: {$name}", true, false];
    }

    /**
     * A compact, schedule-only view of the event for the model and the frontend.
     *
     * @return array<string, mixed>
     */
    public function scheduleSnapshot(Event $event): array
    {
        $event->loadMissing('shows');
        $dates = $event->shows->pluck('date')->map(fn ($d) => (string) $d)->values();

        return [
            'showtype' => $event->showtype,
            'showtype_label' => match ($event->showtype) {
                's' => 'specific dates',
                'o' => 'ongoing/recurring',
                'a' => 'always available',
                default => 'not set',
            },
            'timezone' => $event->timezone,
            'show_count' => $dates->count(),
            'show_dates' => $dates->all(),
            'show_times' => $event->show_times,
            'embargo_date' => $event->embargo_date,
            'closing_date' => $event->closingDate,
        ];
    }

    protected function systemPrompt(Event $event): string
    {
        $snapshot = json_encode($this->scheduleSnapshot($event), JSON_PRETTY_PRINT);
        $nowUtc = now('UTC')->format('Y-m-d H:i:s');

        return <<<PROMPT
        You are a scheduling assistant embedded in the "Dates" step of Everything Immersive's
        event-creation wizard. You help an admin set up ONLY the SCHEDULE of one event —
        show dates/times, recurrence, and the embargo (publish) date. You cannot change the
        name, location, tickets, advisories, or anything else; if asked, say that's outside
        the Dates step.

        The current UTC time is {$nowUtc}. On Everything Immersive a "show" is a CALENDAR DAY,
        not a clock time: show dates are stored in UTC as "Y-m-d H:i:s", anchored at NOON in
        the event's timezone. The real show time(s) live in the free-text show_times field.
        The event has its own timezone.

        ONE SHOW PER DAY — never put two shows on the same date:
        - Each entry in dateArray must be a distinct calendar day. If the user wants more than
          one showing on a day (e.g. a matinee AND an evening), that is still ONE date —
          describe both times in show_times, do NOT add the date twice.
        - Build each dateArray entry as 12:00 (noon) in the event's timezone, converted to UTC.
          Do NOT encode the real show time in the date.
        - Put the real show time(s) in show_times, e.g. "Fridays 7:30 PM" or "Matinee 2 PM,
          evening 8 PM". State the times back to the user to confirm.

        Show types:
        - "s" specific dates: pass every show day as a noon-anchored UTC datetime in dateArray.
        - "o" ongoing/recurring (weekly): pass ongoing_config {startDate, endDate,
          daysOfWeek:[0-6, 0=Sunday]} AND expand the concrete occurrence days into dateArray too.
        - "a" always available: pass always_config {endDate}.
        Patterns that are not weekly (e.g. "every third day") do NOT fit ongoing mode — use
        specific dates ("s") and expand the full list yourself.

        Rules:
        - ALWAYS include showtype in update_schedule whenever you change the dates (even if the
          showtype is not changing). dateArray only takes effect together with showtype.
        - Removing or replacing existing shows is IRREVERSIBLE — there is no undo. If your
          update would delete existing shows (switching show type, or a dateArray that drops
          dates), update_schedule returns action_required=confirm_schedule_replace with the
          count INSTEAD of applying. When that happens: tell the user exactly how many shows
          will be removed, get their explicit "yes", then call again with the same arguments
          plus confirm_schedule_replace=true. Never confirm on the user's behalf. (Adding
          dates without dropping any needs no confirmation.)
        - NEVER schedule a show in the past. Every date must be today or later in the event's
          timezone. If a date you computed lands before the current UTC time above, you got the
          year or a relative date ("this Friday") wrong — recheck it. The tool rejects past
          dates (error=past_dates); if that happens, fix the dates with the user, don't retry
          blindly.
        - An event must always keep at least one date; it cannot have an empty schedule. If the
          user says "remove all the dates" or "clear the schedule", do NOT send an empty
          dateArray — ask which dates should replace the current ones, then send those.
        - For anything ambiguous (which year? what time? how many weeks?), ask a brief question
          instead of guessing.
        - Keep replies short and concrete. After a successful update_schedule, briefly confirm
          what changed (e.g. "Added 12 Friday shows, Aug 1 – Oct 17, 7:30 PM Pacific").
        - Use get_schedule if you need to re-check the current state.

        Current schedule:
        {$snapshot}
        PROMPT;
    }

    /**
     * Anthropic tool definitions. update_schedule intentionally omits event_slug —
     * the event is pinned server-side.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toolDefinitions(): array
    {
        return [
            [
                'name' => 'get_schedule',
                'description' => 'Get the current schedule of the event (show type, timezone, all show dates, show times, embargo date).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name' => 'update_schedule',
                'description' => 'Update the event schedule. Send only the fields you are changing. All datetimes are UTC "Y-m-d H:i:s".',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'showtype' => [
                            'type' => 'string',
                            'enum' => ['s', 'o', 'a'],
                            'description' => 's = specific dates, o = ongoing/recurring (weekly), a = always available. Changing this wipes existing shows and tickets.',
                        ],
                        'timezone' => [
                            'type' => 'string',
                            'description' => 'IANA timezone, e.g. America/Los_Angeles.',
                        ],
                        'dateArray' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Concrete show datetimes in UTC "Y-m-d H:i:s". Required for showtype s and o (expand recurring occurrences yourself).',
                        ],
                        'ongoing_config' => [
                            'type' => 'object',
                            'description' => 'For showtype o. {startDate, endDate as UTC "Y-m-d H:i:s"; daysOfWeek: array of 0-6 with 0=Sunday}.',
                            'properties' => [
                                'startDate' => ['type' => 'string'],
                                'endDate' => ['type' => 'string'],
                                'daysOfWeek' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            ],
                        ],
                        'always_config' => [
                            'type' => 'object',
                            'description' => 'For showtype a. {endDate as UTC "Y-m-d H:i:s"}.',
                            'properties' => [
                                'endDate' => ['type' => 'string'],
                            ],
                        ],
                        'show_times' => [
                            'type' => 'string',
                            'description' => 'Free-text description of show times (max 500 chars).',
                        ],
                        'embargo_date' => [
                            'type' => 'string',
                            'description' => 'UTC "Y-m-d H:i:s" when the event should auto-publish, or null to clear.',
                        ],
                        'confirm_schedule_replace' => [
                            'type' => 'boolean',
                            'description' => 'Set true ONLY after the user confirms deleting existing shows (see the one-per-day / replace confirmation rule).',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Concatenate the text blocks of an assistant content array.
     *
     * @param  array<int, mixed>  $content
     */
    protected function extractText(array $content): string
    {
        $text = collect($content)
            ->filter(fn ($block) => ($block['type'] ?? null) === 'text')
            ->map(fn ($block) => $block['text'] ?? '')
            ->implode("\n\n");

        return trim($text) !== '' ? trim($text) : 'Done.';
    }
}
