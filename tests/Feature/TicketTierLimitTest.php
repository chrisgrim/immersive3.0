<?php

use App\Support\Validation\EventUpdateRules;

/**
 * The 5-tier cap lived only in the wizard UI, with no backend rule behind it,
 * so every non-wizard path ignored it: 26 events already carry more, up to a
 * published one with 9. (593 SHOWS exceed it, but tiers hang off each Show and
 * the wizard copies one set onto every date, so that count multiplies a single
 * event by its number of dates.) It is a real rule now, which means the number
 * has to clear the data that already exists AND the wizard has to agree with
 * it, or someone hits a validation error the UI let them create.
 */
function ticketsVueSource(): string
{
    return file_get_contents(resource_path('js/PageComponents/Creation/Core/Pages/tickets.vue'));
}

test('the wizard offers exactly the number of tiers the backend accepts', function () {
    preg_match('/const MAX_TICKET_TIERS = (\d+);/', ticketsVueSource(), $m);
    expect($m)->not->toBeEmpty('MAX_TICKET_TIERS not found in tickets.vue — was it renamed?');

    expect((int) $m[1])->toBe(EventUpdateRules::MAX_TICKET_TIERS);
});

test('the wizard gates its add-tier button on the shared constant, not a literal', function () {
    // The regression this guards: the cap was two hardcoded 5s. Raising the
    // backend alone would leave the button capped, and vice versa.
    expect(ticketsVueSource())
        ->toContain('v-if="tickets.length < MAX_TICKET_TIERS"')
        ->toContain('if (tickets.length < MAX_TICKET_TIERS) {');
});

test('the cap clears the largest tier count already in production', function () {
    // A published 2022 event ("Currents: Niagara's Power Transformed") carries
    // 9 legitimate tiers — admission vs guided tour, each split adult/child,
    // plus packages and a free under-5. A cap below that would fail validation
    // the next time a moderator saved it.
    expect(EventUpdateRules::MAX_TICKET_TIERS)->toBeGreaterThanOrEqual(9);
});

test('the validation rule caps the tier array', function () {
    expect(EventUpdateRules::rules()['tickets'])
        ->toBe('nullable|array|max:'.EventUpdateRules::MAX_TICKET_TIERS);
});

test('the cap is explained in the error message rather than a bare validation failure', function () {
    expect(EventUpdateRules::messages()['tickets.max'])
        ->toContain((string) EventUpdateRules::MAX_TICKET_TIERS);
});

test('the MCP tool advertises the same limit and currency list as the validator', function () {
    // This description told API clients "1-5 ticket tiers" and listed only six
    // currencies, having missed CN¥ and ₩ when they were added — a fourth copy
    // of both facts, drifting silently. It derives from the constants now.
    $source = file_get_contents(app_path('Mcp/Tools/UpdateEvent.php'));

    expect($source)
        ->toContain("'1-'.EventUpdateRules::MAX_TICKET_TIERS.' ticket tiers")
        ->toContain("implode(' ', EventUpdateRules::CURRENCIES)");
});
