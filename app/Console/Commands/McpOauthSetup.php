<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use RuntimeException;

/**
 * Make sure OAuth can work on this environment: the RSA key pair Passport
 * signs tokens with, and the personal-access client the API keys page mints
 * tokens through. Idempotent, so the deploy runs it after every migrate.
 *
 * Keys live in storage/ (persisted across deploys, git-ignored). The deploy
 * never regenerates existing keys: doing so would invalidate every token in
 * circulation.
 */
class McpOauthSetup extends Command
{
    protected $signature = 'mcp:oauth-setup {--length=4096 : RSA key length, if keys need generating}';

    protected $description = 'Ensure the OAuth key pair and personal-access client exist (idempotent; runs on deploy)';

    public function handle(ClientRepository $clients): int
    {
        $private = Passport::keyPath('oauth-private.key');
        $public = Passport::keyPath('oauth-public.key');

        if (is_file($private) && is_file($public)) {
            $this->components->info('Keys: present ('.dirname($private).')');
        } else {
            $this->call('passport:keys', ['--length' => (int) $this->option('length')]);
            $this->components->info('Keys: generated ('.dirname($private).')');
        }

        $provider = config('auth.guards.api.provider', 'users');

        try {
            $client = $clients->personalAccessClient($provider);
            $this->components->info("Personal access client: present ({$client->getKey()})");
        } catch (RuntimeException) {
            $client = $clients->createPersonalAccessGrantClient('Everything Immersive API keys', $provider);
            $this->components->info("Personal access client: created ({$client->getKey()})");
        }

        return self::SUCCESS;
    }
}
