<?php

use App\Http\Requests\StoreEventRequest;

/**
 * Tripwire for the regression that hit prod on 2026-05-27.
 *
 * The Vue event-creation wizard encodes a STEP_MAP — step name → status char —
 * in resources/js/PageComponents/Creation/Core/index.vue. As the user advances
 * each step, the wizard POSTs `status = STEP_MAP[currentStep]` and the value
 * has to clear the `status` allow-list in StoreEventRequest.
 *
 * Two independent files, no shared source of truth. CR1's allow-list
 * tightening (70a502b) plus its 513c6a7 follow-up missed 'A' (Advisories)
 * and 'D' (Review), and users got stuck mid-wizard with no clue why. If
 * STEP_MAP gains a new value in the future, this test fails until the
 * form-request allow-list is updated to match.
 */
test('every STEP_MAP value the wizard can POST is in the status allow-list', function () {
    $vueFile = base_path('resources/js/PageComponents/Creation/Core/index.vue');
    $vueSource = file_get_contents($vueFile);

    // Extract the STEP_MAP literal. The map is small and lives in one block.
    expect($vueSource)->toMatch('/const STEP_MAP = \{[^}]+\}/');
    preg_match('/const STEP_MAP = \{([^}]+)\}/', $vueSource, $mapMatch);
    preg_match_all("/'[A-Za-z]+'\s*:\s*'([^']+)'/", $mapMatch[1], $valueMatches);
    $stepValues = array_unique($valueMatches[1]);

    expect($stepValues)->not->toBeEmpty('STEP_MAP parse returned no values — regex likely needs updating');

    // Extract the status rule's allow-list from StoreEventRequest.
    $rules = (new StoreEventRequest)->rules();
    expect($rules)->toHaveKey('status');
    preg_match('/\bin:([^|]+)/', $rules['status'], $inMatch);
    expect($inMatch)->not->toBeEmpty('status rule has no in: clause');
    $allowed = array_map('trim', explode(',', $inMatch[1]));

    $missing = array_values(array_diff($stepValues, $allowed));
    expect($missing)->toBe(
        [],
        'STEP_MAP values not in status allow-list: '.implode(', ', $missing)
        ."\nUpdate StoreEventRequest::rules() `status` in: clause to match"
        ." or remove the value from STEP_MAP in Core/index.vue."
    );
});
