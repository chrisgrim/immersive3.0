<?php

use App\Models\Event;
use App\Support\Validation\EventUpdateRules;
use Illuminate\Support\Facades\Validator;

/**
 * Some shows have no website and sell tickets only by email. The wizard used
 * to force "https://" onto whatever was typed, so the one such organizer who
 * tried saved "https://mailto:…", a button that opened an error page.
 */
function ticketUrlPasses(?string $value): bool
{
    $rules = ['ticketUrl' => EventUpdateRules::rules()['ticketUrl']];

    return Validator::make(['ticketUrl' => $value], $rules)->passes();
}

test('a ticket link may be a web address or an email link', function (string $value) {
    expect(ticketUrlPasses($value))->toBeTrue();
})->with([
    'https://example.com/tickets',
    'http://example.com',
    'mailto:weirdsisters@gmail.com',
    'MAILTO:someone@example.org',
]);

test('broken ticket links are refused', function (string $value) {
    expect(ticketUrlPasses($value))->toBeFalse();
})->with([
    'https://mailto:weirdsisters@gmail.com',
    'mailto:not-an-email',
    'mailto:',
    'just some words',
]);

test('an empty ticket link is still allowed on save', function () {
    expect(ticketUrlPasses(null))->toBeTrue();
});

test('ticketsByEmail spots mailto links only', function () {
    expect((new Event(['ticketUrl' => 'mailto:a@b.com']))->ticketsByEmail())->toBeTrue()
        ->and((new Event(['ticketUrl' => 'https://b.com']))->ticketsByEmail())->toBeFalse()
        ->and((new Event(['ticketUrl' => null]))->ticketsByEmail())->toBeFalse();
});
