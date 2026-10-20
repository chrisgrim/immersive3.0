<?php

use App\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Http\Request;

// An admin whose Safari sends no Referer got 401 on every dashboard API call.

function frontendRequest(array $headers): Request
{
    $request = Request::create('https://'.parse_url(config('app.url'), PHP_URL_HOST).'/api/admin/approval-counts');
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

it('treats a same-origin fetch with no Referer as coming from our pages', function () {
    expect(EnsureFrontendRequestsAreStateful::fromFrontend(frontendRequest(['Sec-Fetch-Site' => 'same-origin'])))->toBeTrue();
});

it('still accepts our own Referer', function () {
    expect(EnsureFrontendRequestsAreStateful::fromFrontend(frontendRequest(['Referer' => config('app.url').'/admin/dashboard'])))->toBeTrue();
});

it('does not treat other sites or bare clients as ours', function (array $headers) {
    expect(EnsureFrontendRequestsAreStateful::fromFrontend(frontendRequest($headers)))->toBeFalse();
})->with([
    'no headers' => [[]],
    'cross-site' => [['Sec-Fetch-Site' => 'cross-site']],
    'same-site subdomain' => [['Sec-Fetch-Site' => 'same-site']],
    'other referer' => [['Referer' => 'https://evil.example/page']],
]);
