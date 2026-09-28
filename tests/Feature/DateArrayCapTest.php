<?php

use App\Models\Events\Show;
use App\Support\RecurringDates;
use App\Support\Validation\EventUpdateRules;

/**
 * A hand-written dateArray (web wizard or MCP) gets the same total ceiling as
 * a recurrence, and its days from a year ago on (the ones stored as rows, and
 * so indexed for search) get Show::MAX_ROWS.
 */
function dateArrayOf(int $count): array
{
    $start = now()->startOfDay();

    return collect(range(0, $count - 1))
        ->map(fn ($i) => $start->copy()->addDays($i)->format('Y-m-d 00:00:00'))
        ->all();
}

test('a dateArray at the row cap passes and one over it fails', function () {
    // Every one of these is upcoming, so every one would be a row.
    $validate = fn (int $count) => validator(
        ['showtype' => 's', 'dateArray' => dateArrayOf($count)],
        ['dateArray' => EventUpdateRules::rules()['dateArray']],
    );

    expect($validate(Show::MAX_ROWS)->passes())->toBeTrue()
        ->and($validate(Show::MAX_ROWS + 1)->passes())->toBeFalse();
});

test('a dateArray may not exceed the total cap however old its days are', function () {
    $start = now()->subYears(110)->startOfDay();
    $dates = collect(range(0, RecurringDates::MAX_OCCURRENCES))
        ->map(fn ($i) => $start->copy()->addDays($i)->format('Y-m-d 00:00:00'))
        ->all();

    expect(validator(['showtype' => 's', 'dateArray' => $dates], ['dateArray' => EventUpdateRules::rules()['dateArray']])->passes())
        ->toBeFalse();
});
