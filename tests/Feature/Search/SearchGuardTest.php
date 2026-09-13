<?php

use App\Support\Search\SearchGuard;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Support\Facades\Exceptions;

/**
 * The search page and the nav autocomplete used to 500 whenever
 * Elasticsearch was unreachable (a known ~55 s window after the nightly
 * reboot). SearchGuard turns a cluster failure into the fallback value
 * and a Sentry report; anything else still propagates.
 */
test('returns the fallback and reports when no node is available', function () {
    Exceptions::fake();

    $result = SearchGuard::run(function () {
        throw new NoNodeAvailableException('No alive nodes');
    }, []);

    expect($result)->toBe([]);
    Exceptions::assertReported(NoNodeAvailableException::class);
});

test('invokes a Closure fallback', function () {
    Exceptions::fake();

    $result = SearchGuard::run(function () {
        throw new NoNodeAvailableException('No alive nodes');
    }, fn () => collect(['fallback']));

    expect($result->all())->toBe(['fallback']);
});

test('treats an Elasticsearch server error as a search failure', function () {
    Exceptions::fake();

    $result = SearchGuard::run(function () {
        throw new ServerResponseException('500 from the cluster');
    }, 0.0);

    expect($result)->toBe(0.0);
    Exceptions::assertReported(ServerResponseException::class);
});

test('returns the query result untouched on success', function () {
    expect(SearchGuard::run(fn () => 42, 0))->toBe(42);
});

test('lets unrelated exceptions propagate', function () {
    Exceptions::fake();

    expect(fn () => SearchGuard::run(function () {
        throw new RuntimeException('not a search problem');
    }, []))->toThrow(RuntimeException::class);

    Exceptions::assertNothingReported();
});
