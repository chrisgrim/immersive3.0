<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Client;

/**
 * Dynamic client registration (POST /oauth/register) is open to anyone who
 * can reach the site, and every call leaves a row. Clients that never went
 * on to obtain a code or a token within a day are litter; sweep them.
 */
class PruneOauthClients extends Command
{
    protected $signature = 'mcp:prune-oauth-clients {--hours=24 : Age before an unused registration is removed}';

    protected $description = 'Delete OAuth clients registered dynamically that never obtained a code or token';

    public function handle(): int
    {
        $stale = Client::query()
            ->whereJsonContains('grant_types', 'authorization_code')
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->whereDoesntHave('tokens')
            ->whereDoesntHave('authCodes')
            ->get();

        $stale->each->delete();

        $this->components->info("Pruned {$stale->count()} unused OAuth client registration(s).");

        return self::SUCCESS;
    }
}
