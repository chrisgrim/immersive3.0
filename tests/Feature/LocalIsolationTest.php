<?php

use App\Support\LocalIsolation;

test('a local copy with local settings starts', function () {
    expect(fn () => LocalIsolation::check())->not->toThrow(RuntimeException::class);
});

test('the database really is this machine', function () {
    expect(LocalIsolation::databaseProblem())->toBeNull();
});

test('a tunnel to another server\'s database is caught, even on 127.0.0.1', function () {
    Illuminate\Support\Facades\DB::shouldReceive('selectOne')->andReturn((object) ['h' => 'the-droplet']);
    expect(LocalIsolation::databaseProblem())->toContain('the-droplet');
});

test('a local copy refuses any bucket but the test one, in any case', function () {
    foreach (['ei-prod', 'EI-PROD', 'some-other-bucket'] as $bucket) {
        config(['filesystems.disks.digitalocean.bucket' => $bucket]);
        expect(LocalIsolation::configProblems())->not->toBe([]);
    }
});

test('a local copy refuses an endpoint that names the live bucket', function () {
    config(['filesystems.disks.digitalocean.endpoint' => 'https://ei-prod.sfo3.digitaloceanspaces.com']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'endpoint');
});

test('a local copy refuses a live bucket on the s3 disk too', function () {
    config(['filesystems.disks.s3.bucket' => 'ei-prod']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 's3 disk');
});

test('a local copy refuses a remote database host', function () {
    config(['database.connections.'.config('database.default').'.host' => 'db.example.com']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'database host');
});

test('a local copy refuses a DB_URL that points elsewhere', function () {
    config(['database.connections.'.config('database.default').'.url' => 'mysql://u:p@db.example.com:3306/ei']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'DB_URL');
});

test('a local copy refuses the live address', function () {
    config(['app.url' => 'https://everythingimmersive.com']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'live site');
});

test('a local copy refuses a real mailer', function () {
    config(['mail.default' => 'smtp']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'really send');
});

test('on a Mac, calling it production does not switch the check off', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['mail.default' => 'smtp']);
    expect(fn () => LocalIsolation::check())->toThrow(RuntimeException::class, 'really send');
})->skip(PHP_OS_FAMILY !== 'Darwin', 'only meaningful on a Mac');

test('the live and dev servers are never checked, any Mac always is', function () {
    // The droplet is Linux with APP_ENV production (live) or staging (dev).
    expect(LocalIsolation::appliesTo('production', 'Linux'))->toBeFalse();
    expect(LocalIsolation::appliesTo('staging', 'Linux'))->toBeFalse();
    expect(LocalIsolation::appliesTo('local', 'Linux'))->toBeTrue();
    expect(LocalIsolation::appliesTo('testing', 'Linux'))->toBeTrue();
    expect(LocalIsolation::appliesTo('production', 'Darwin'))->toBeTrue();
});
