<?php

namespace App\Support\Validation;

use App\Rules\ZeroDecimalPriceRule;

/**
 * The canonical partial-update validation contract for events.
 *
 * Shared by the web wizard (StoreEventRequest) and the MCP tools so the two
 * write paths can never drift. Every rule is `sometimes`/nullable — callers
 * send only the fields they are changing.
 */
class EventUpdateRules
{
    /**
     * The only currencies a ticket may be stored in — exactly the symbols the
     * wizard's picker offers (CURRENCY_SYMBOLS in tickets.vue). The value is
     * rendered verbatim next to the price, so anything else shows up on the
     * live listing as-is: an API client sending the ISO code "USD" produced
     * "USD17.00" where "$17.00" was meant. `max:3` accepted it because the code
     * is three characters long.
     *
     * 'CN¥' (not bare '¥') for yuan — CNY and JPY share the same glyph, and
     * '¥' was already live for JPY, so yuan gets the disambiguating prefix
     * instead of touching JPY's existing stored value, same precedent as
     * 'MX$' getting the prefix rather than USD's already-live '$'.
     *
     * @var array<string, string>
     */
    public const CURRENCY_ISO = [
        '$' => 'USD',
        '€' => 'EUR',
        '£' => 'GBP',
        '¥' => 'JPY',
        'C$' => 'CAD',
        'MX$' => 'MXN',
        'CN¥' => 'CNY',
        '₩' => 'KRW',
    ];

    /**
     * The symbols themselves, derived so this can never disagree with the
     * map above. Adding a currency means adding ONE line to CURRENCY_ISO.
     *
     * This is the single source of truth for which currencies exist. The
     * event page's JSON-LD reads CURRENCY_ISO directly, and the wizard's own
     * picker list (tickets.vue) is asserted against both by
     * tests/Feature/CurrencyCatalogTest.php, since a Vue constant can't
     * import a PHP one — the list used to be maintained by hand in four
     * separate places with nothing checking they agreed.
     *
     * @var array<int, string>
     */
    public const CURRENCIES = [
        '$', '€', '£', '¥', 'C$', 'MX$', 'CN¥', '₩',
    ];

    /**
     * Currencies with no minor unit, where a price is written without decimals.
     *
     * JPY, CNY and KRW have no "cents", so "¥1234.00" and "₩144000.00" are
     * both wrong — the trailing .00 is not how those amounts are ever
     * written. The list was inline in six places (the event page's blade
     * twice, show-purchase.vue and its mobile twin, navSidebar.vue,
     * review.vue, EventReview.vue) before it was needed in the wizard's
     * price input as well, which would have made seven.
     *
     * The blade reads this directly; the Vue copies can't import a PHP
     * constant, so tests/Feature/CurrencyCatalogTest.php asserts each of them
     * against it — the same arrangement CURRENCY_ISO already has.
     *
     * @var array<int, string>
     */
    public const ZERO_DECIMAL_CURRENCIES = ['¥', 'CN¥', '₩'];

    /**
     * How many decimal places a price in this currency is written with.
     */
    public static function decimalsFor(?string $currency): int
    {
        return in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true) ? 0 : 2;
    }

    /**
     * How many ticket tiers one show may carry.
     *
     * This was 5, enforced ONLY in the wizard UI (tickets.vue) with nothing
     * behind it, so every non-wizard path (MCP tools, the API, the scraper)
     * ignored it. Reported by a moderator who found a 6-tier event.
     *
     * Scale, stated carefully: 593 SHOWS exceed 5 tiers, but that is only 26
     * distinct events — tiers hang off each Show, and the wizard copies one
     * tier set onto every show date, so a single event with many dates counts
     * many times over. Only ONE of those 26 is still open ("Artmosphere",
     * closes 2027). Nobody is entering nine tickets per date.
     *
     * 10, not 8: the highest real case is a published 2022 event with 9
     * legitimate tiers (admission vs guided tour, each split adult/child, plus
     * packages and a free under-5). Now that the cap is actually enforced, one
     * set below the largest existing show would fail validation the next time
     * anyone saved that event.
     *
     * Keep tickets.vue's own MAX_TICKET_TIERS in step — asserted by
     * tests/Feature/TicketTierLimitTest.php, since a Vue constant can't import
     * a PHP one.
     */
    public const MAX_TICKET_TIERS = 10;

    /**
     * The highest price a single ticket tier may carry.
     *
     * This was 99999.99, which is a fine ceiling for a currency with a minor
     * unit and a nonsense one for a currency without: a normal ticket to
     * Sleep No More Seoul is 144,000 KRW (~$105), and it was rejected. The
     * wizard was worse than a rejection — its input capped the integer part at
     * five digits, so typing 144000 silently became 14400 and saved a tenth of
     * the real price.
     *
     * 999999.99 is the largest value the `tickets.ticket_price` column holds
     * (decimal(8,2)), so raising to exactly the column ceiling needs no
     * migration. It clears the zero-decimal currencies by a wide margin
     * (999,999 KRW is ~$720 a head) while still catching the fat-finger and
     * overflow cases the cap is actually for.
     *
     * Keep tickets.vue's own MAX_TICKET_PRICE in step — asserted by
     * tests/Feature/CurrencyCatalogTest.php, since a Vue constant can't
     * import a PHP one.
     */
    public const MAX_TICKET_PRICE = 999999.99;

    /**
     * Ticket currencies an API/MCP client is likely to send instead of the
     * symbol we store, mapped to that symbol. The wizard can only emit the
     * symbols above, so this exists for non-browser callers — normalising an
     * unambiguous ISO code beats rejecting it and making the client guess.
     *
     * @var array<string, string>
     */
    public const CURRENCY_ALIASES = [
        'USD' => '$', 'US$' => '$', 'DOLLAR' => '$', 'DOLLARS' => '$',
        'EUR' => '€', 'EURO' => '€', 'EUROS' => '€',
        'GBP' => '£', 'POUND' => '£', 'POUNDS' => '£', 'QUID' => '£',
        'JPY' => '¥', 'YEN' => '¥',
        'CAD' => 'C$', 'CA$' => 'C$', 'CAD$' => 'C$', 'C' => 'C$',
        'MXN' => 'MX$', 'MXN$' => 'MX$', 'MX' => 'MX$', 'PESO' => 'MX$', 'PESOS' => 'MX$',
        'CNY' => 'CN¥', 'RMB' => 'CN¥', 'YUAN' => 'CN¥',
        'KRW' => '₩', 'WON' => '₩',
    ];

    /**
     * Resolve a caller-supplied currency to the symbol we store, or return it
     * unchanged so validation can reject it with the list of valid options.
     */
    public static function normalizeCurrency(mixed $currency): mixed
    {
        if (! is_string($currency)) {
            return $currency;
        }

        $trimmed = trim($currency);

        if (in_array($trimmed, self::CURRENCIES, true)) {
            return $trimmed;
        }

        return self::CURRENCY_ALIASES[mb_strtoupper($trimmed)] ?? $currency;
    }

    public static function rules(): array
    {
        return [
            'category_id' => 'nullable|exists:categories,id',
            'attendance_type_id' => 'nullable|exists:attendance_types,id',
            'interactive_level_id' => 'nullable|exists:interactive_levels,id',
            'description' => 'sometimes|string|min:1|max:5000',
            'name' => 'sometimes|string|max:100|regex:/[\p{L}\p{N}]/u',
            // closingDate is DERIVED from the schedule (Show::calculateLastDate),
            // never submitted. Accepting it let one field revive a finished
            // event: POST {"closingDate": "2030-01-01"} put it back in search
            // and listings while every show stayed in the past — a run that
            // has ended, rendered as live, and invisible to date search. The
            // MCP tool already refused it for exactly this reason
            // (UpdateEvent::DERIVED); the web path never did.
            'websiteUrl' => 'sometimes|url|max:255',
            'ticketUrl' => 'nullable|url|max:255',
            'show_times' => 'nullable|string|max:500',
            'tag_line' => 'sometimes|string|max:255',
            'hasLocation' => 'sometimes|boolean',
            'embargo_date' => 'nullable|date_format:Y-m-d H:i:s|after:now',
            'remote_description' => 'nullable|string|max:3000',
            'call_to_action' => 'nullable|string|max:255',
            'video' => 'nullable|string|max:255',
            'archived' => 'nullable|boolean',
            // Add nested validation rules for location
            'location.latitude' => 'sometimes|numeric',
            'location.longitude' => 'sometimes|numeric',
            'location.home' => 'sometimes|string|max:255',
            'location.street' => 'sometimes|string|max:255',
            'location.city' => 'sometimes|string|max:255',
            'location.region' => 'sometimes|string|max:255',
            'location.region_long' => 'sometimes|string|max:255',
            'location.country' => 'sometimes|string|max:255',
            'location.country_long' => 'sometimes|string|max:255',
            'location.postal_code' => 'sometimes|nullable|string|max:20',
            'location.hiddenLocation' => 'nullable|string|max:255',
            'location.hiddenLocationToggle' => 'sometimes|boolean',
            // 80, not 255: the wizard's venue input is maxlength=80 and its
            // Location step refuses to advance past a longer one, so a value the
            // server accepted but the form could not was a Next button that did
            // nothing. The longest venue on record is 65 characters.
            'location.venue' => 'nullable|string|max:80',
            // Add validation for remotelocations
            'remotelocations' => 'nullable|array',
            'remotelocations.*.name' => 'required_with:remotelocations|string|max:255',
            // Add validation for dateArray
            'timezone' => 'sometimes|string|max:255',
            'showtype' => 'sometimes|string|in:s,a,o',
            // Statuses a user is allowed to set/persist on their own event:
            //   'd' draft, '0'-'9' wizard step markers, 'A'-'D' wizard step
            //   markers for Advisories/Content/Mobility/Review (see STEP_MAP
            //   in resources/js/PageComponents/Creation/Core/index.vue).
            // 'p' (published), 'e' (embargoed), 'r' (in-review), 'n' (needs
            // revision) are deliberately excluded — those transitions go
            // through the dedicated submit() / approve() / reject() endpoints.
            'status' => 'sometimes|string|in:d,0,1,2,3,4,5,6,7,8,9,A,B,C,D',
            'dateArray' => [
                'required_if:showtype,s',
                'array',
            ],
            'dateArray.*' => 'required_if:showtype,s|date_format:Y-m-d H:i:s',
            // Ongoing/always config (used by M11 showtype_config persistence)
            'ongoing_config' => 'sometimes|nullable|array',
            'ongoing_config.startDate' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
            'ongoing_config.endDate' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
            'ongoing_config.daysOfWeek' => 'sometimes|nullable|array',
            'ongoing_config.daysOfWeek.*' => 'integer|between:0,6',
            'always_config' => 'sometimes|nullable|array',
            'always_config.endDate' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
            // Add validation for tickets
            'tickets' => 'nullable|array|max:'.self::MAX_TICKET_TIERS,
            // `sometimes` here used to mean an omitted price or currency passed
            // validation and then blew up in Ticket::handleTickets, which reads
            // both keys unconditionally. The web wizard always sends all four
            // (it normalizes missing values to ''), so only API/MCP clients can
            // send a partial tier — they get a field error now instead of an
            // "Undefined array key" 500.
            'tickets.*.name' => 'required_with:tickets|string|max:40',
            'tickets.*.description' => 'sometimes|nullable|string|max:200',
            'tickets.*.currency' => 'required_with:tickets|string|in:'.implode(',', self::CURRENCIES),
            // A currency with no minor unit has no fractional price — see
            // ZeroDecimalPriceRule for why this is rejected on the way in
            // rather than rounded on the way out.
            'tickets.*.ticket_price' => [
                'required_with:tickets', 'numeric', 'min:0', 'max:'.self::MAX_TICKET_PRICE,
                new ZeroDecimalPriceRule,
            ],
            // Relaxed validation for images
            'images' => 'nullable|array',
            'images.*' => [
                'file',
                'mimes:jpeg,png,jpg,webp',
                'max:5120',
                // Removed dimensions validation as it seems to cause issues
            ],
            'ranks' => 'nullable|array',
            'ranks.*' => 'integer|min:0|max:4',
            'currentImages' => 'nullable|json',
            'deletedImages' => 'nullable|json',
            // Add validation for contentAdvisories
            'contentAdvisories' => 'nullable|array|max:16',
            'contentAdvisories.*.name' => 'sometimes|string|max:100',
            // Add validation for mobilityAdvisories
            'mobilityAdvisories' => 'nullable|array|max:16',
            'mobilityAdvisories.*.name' => 'sometimes|string|max:100',
            'wheelchairReady' => 'sometimes|boolean',
            // Add validation for contact and interactive levels
            'contactLevel' => 'sometimes|array',
            'contactLevel.id' => 'required_with:contactLevel|exists:contact_levels,id',
            'contactLevel.name' => 'required_with:contactLevel|string|max:255',

            'interactiveLevel' => 'sometimes|array',
            'interactiveLevel.id' => 'required_with:interactiveLevel|exists:interactive_levels,id',
            'interactiveLevel.name' => 'required_with:interactiveLevel|string|max:255',
            'interactiveLevel.description' => 'required_with:interactiveLevel|string',

            // Add audience role validation
            'advisories' => 'sometimes|array',
            'advisories.sexual' => 'sometimes|boolean',
            'advisories.sexualDescription' => 'nullable|string|max:1000|required_if:advisories.sexual,true',
            'advisories.audience' => 'sometimes|string|max:1000',

            // Add validation for genres
            'genres' => 'sometimes|array|max:10',
            'genres.*.id' => 'sometimes|required',
            'genres.*.name' => 'required|string|max:50',

            // Note: `status` rule is defined earlier with an allow-list (in:d,0-8).

            // Add this to your rules array
            'ageLimit.id' => 'sometimes|exists:age_limits,id',

            // Add validation for videos
            'videos' => 'nullable|json',
        ];
    }

    public static function attributes(): array
    {
        return [
            'location.postal_code' => 'postal code',
        ];
    }

    public static function messages(): array
    {
        return [
            'images.*.dimensions' => 'Images must be at least 400x400 pixels and no larger than 10000x10000 pixels.',
            'contentAdvisories.max' => 'You can select a maximum of 16 content advisories.',
            'mobilityAdvisories.max' => 'You can select a maximum of 16 mobility advisories.',
            'name.regex' => 'The name must contain at least one letter or number.',
            'tickets.max' => 'An event can have at most '.self::MAX_TICKET_TIERS.' ticket tiers.',
            'tickets.*.ticket_price.max' => 'A ticket price cannot exceed '.number_format(self::MAX_TICKET_PRICE, 2).'.',
            'tickets.*.currency.in' => 'Ticket prices must use one of these currency symbols: '.implode(' ', self::CURRENCIES).'. Use the symbol, not the ISO code — "$", not "USD".',
        ];
    }
}
