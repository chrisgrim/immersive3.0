<?php

use App\Support\LocalIsolation;

test('a local copy with local settings starts', function () {
    expect(fn () => LocalIsolation::check())->not->toThrow(RuntimeException::class);
});

test('a local copy refuses the live bucket', function () {
    config(['filesystems.disks.digitalocean.bucket' => 'ei-prod']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'live bucket');
});

test('a local copy refuses a remote database', function () {
    config(['database.connections.'.config('database.default').'.host' => 'db.example.com']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'database host');
});

test('a local copy refuses the live address', function () {
    config(['app.url' => 'https://everythingimmersive.com']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'live site');
});

test('a local copy refuses a real mailer', function () {
    config(['mail.default' => 'smtp']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'really send');
});

test('production is never checked', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['filesystems.disks.digitalocean.bucket' => 'ei-prod', 'mail.default' => 'smtp']);
    expect(fn () => LocalIsolation::check())->not->toThrow(RuntimeException::class);
});
