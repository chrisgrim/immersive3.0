<?php

use Elastic\Client\ClientBuilder;

test('the search client has a timeout, so a hung cluster cannot hold a save', function () {
    expect(config('elastic.client.connections.default.httpClientOptions'))
        ->toBe(['timeout' => 10, 'connect_timeout' => 2]);
});

test('the option reaches the HTTP client: a real hung socket gives up on time', function () {
    // Accepts the connection and never answers.
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);

    config([
        'elastic.client.connections.default.hosts' => [$address],
        'elastic.client.connections.default.httpClientOptions.timeout' => 1,
    ]);

    $started = microtime(true);
    $failed = null;

    try {
        (new ClientBuilder)->default()->info();
    } catch (Throwable $e) {
        $failed = $e;
    }

    $elapsed = microtime(true) - $started;
    fclose($socket);

    // One timeout: the node is then marked down, so the retry fails at once.
    expect($failed)->toBeInstanceOf(Throwable::class)
        ->and($elapsed)->toBeGreaterThan(0.9)->toBeLessThan(3.0);
});

test('an index bulk stays small enough for that timeout', function () {
    expect(config('scout.chunk.searchable'))->toBeLessThanOrEqual(100);
});
