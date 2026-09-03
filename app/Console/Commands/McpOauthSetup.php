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
        } elseif (is_file($private) || is_file($public)) {
            // Half a pair means something went wrong once; generating over it
            // would silently orphan every token signed with the old private key.
            $this->components->error('Keys: only one of oauth-private.key / oauth-public.key exists in '.dirname($private).'. Restore the other from backup, or remove both to start fresh.');

            return self::FAILURE;
        } else {
            if ($this->call('passport:keys', ['--length' => (int) $this->option('length')]) !== self::SUCCESS) {
                $this->components->error('Keys: passport:keys failed.');

                return self::FAILURE;
            }
            $this->components->info('Keys: generated ('.dirname($private).')');
        }

        // PHP-FPM has to read them. The deploy runs this as root and chowns
        // afterwards; say what the files look like so a wrong owner is
        // visible in the deploy log rather than as a 500 on the first token.
        $owner = function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($private))['name'] ?? fileowner($private)) : fileowner($private);
        $this->components->info(sprintf('Keys: owner %s, modes %o / %o', $owner, fileperms($private) & 0777, fileperms($public) & 0777));

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
