<?php

use Laravel\Passport\Client;
use Laravel\Passport\Passport;

/**
 * mcp:oauth-setup runs on every deploy. It must create what is missing and
 * leave alone what exists — regenerating keys would invalidate every token.
 */
test('it creates the key pair and personal-access client once, then leaves them alone', function () {
    $dir = sys_get_temp_dir().'/ei-oauth-setup-'.uniqid();
    mkdir($dir, 0700, true);
    Passport::loadKeysFrom($dir);

    try {
        $this->artisan('mcp:oauth-setup', ['--length' => 2048])
            ->assertSuccessful()
            ->expectsOutputToContain('Keys: generated')
            ->expectsOutputToContain('Personal access client: created');

        expect(is_file($dir.'/oauth-private.key'))->toBeTrue();
        expect(is_file($dir.'/oauth-public.key'))->toBeTrue();
        $before = md5_file($dir.'/oauth-private.key');
        expect(Client::all()->filter(fn (Client $c) => $c->hasGrantType('personal_access'))->count())->toBe(1);

        $this->artisan('mcp:oauth-setup', ['--length' => 2048])
            ->assertSuccessful()
            ->expectsOutputToContain('Keys: present')
            ->expectsOutputToContain('Personal access client: present');

        expect(md5_file($dir.'/oauth-private.key'))->toBe($before);
        expect(Client::all()->filter(fn (Client $c) => $c->hasGrantType('personal_access'))->count())->toBe(1);
    } finally {
        passportTestKeys();
        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);
    }
});
