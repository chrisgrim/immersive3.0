<?php

use App\Support\RecurringDates;
use App\Support\Validation\EventUpdateRules;

/**
 * A hand-written dateArray (web wizard or MCP) gets the same ceiling as a
 * recurrence: more shows than that could never be indexed for search.
 */
function dateArrayOf(int $count): array
{
    $start = now()->startOfDay();

    return collect(range(0, $count - 1))
        ->map(fn ($i) => $start->copy()->addDays($i)->format('Y-m-d 00:00:00'))
        ->all();
}

test('a dateArray at the cap passes and one over it fails', function () {
    $validate = fn (int $count) => validator(
        ['showtype' => 's', 'dateArray' => dateArrayOf($count)],
        ['dateArray' => EventUpdateRules::rules()['dateArray']],
    );

    expect($validate(RecurringDates::MAX_OCCURRENCES)->passes())->toBeTrue()
        ->and($validate(RecurringDates::MAX_OCCURRENCES + 1)->passes())->toBeFalse();
});
