<?php

use App\Support\Search\SearchGuard;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\InvalidArgumentException as TransportInvalidArgumentException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Support\Facades\Exceptions;

/**
 * The search page and the nav autocomplete used to 500 whenever
 * Elasticsearch was unreachable (a known ~55 s window after the nightly
 * reboot). SearchGuard turns a cluster failure into the fallback value
 * and a Sentry report; anything else still propagates.
 */
beforeEach(fn () => SearchGuard::reset());

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

test('lets a 4xx from a bad query propagate: that is our bug, not an outage', function () {
    Exceptions::fake();

    expect(fn () => SearchGuard::run(function () {
        throw new ClientResponseException('400 bad request');
    }, []))->toThrow(ClientResponseException::class);
});

test('after one failure, later reads in the same request short-circuit without retrying or re-reporting', function () {
    Exceptions::fake();
    $attempts = 0;
    $query = function () use (&$attempts) {
        $attempts++;
        throw new NoNodeAvailableException('No alive nodes');
    };

    SearchGuard::run($query, 'a');
    $second = SearchGuard::run($query, 'b');
    $third = SearchGuard::run(fn () => 'would have worked', 'c');

    expect($attempts)->toBe(1);
    expect($second)->toBe('b');
    expect($third)->toBe('c');
    Exceptions::assertReportedCount(1);
});

test('lets a transport configuration error propagate: that is a bug, not an outage', function () {
    Exceptions::fake();

    expect(fn () => SearchGuard::run(function () {
        throw new TransportInvalidArgumentException('bad transport config');
    }, []))->toThrow(TransportInvalidArgumentException::class);

    Exceptions::assertNothingReported();
});

test('failedThisRequest reports whether any guarded read failed in this request', function () {
    Exceptions::fake();

    expect(SearchGuard::failedThisRequest())->toBeFalse();
    SearchGuard::run(fn () => 'fine', null);
    expect(SearchGuard::failedThisRequest())->toBeFalse();

    SearchGuard::run(function () {
        throw new NoNodeAvailableException('No alive nodes');
    }, null);
    expect(SearchGuard::failedThisRequest())->toBeTrue();
});
