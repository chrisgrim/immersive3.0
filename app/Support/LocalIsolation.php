<?php

namespace App\Support;

use RuntimeException;

/**
 * A local copy of the site runs against a copy of the live database, so it must
 * never be able to reach live: not its database, its photo bucket, its address,
 * or real people's inboxes (Chris, 2026-09-30: "nothing and I mean nothing can go
 * from our local to the prod server"). Checked at boot on local and testing only;
 * a wrong .env fails loudly instead of quietly touching live.
 */
class LocalIsolation
{
    public const LIVE_BUCKET = 'ei-prod';

    public const LIVE_DOMAIN = 'everythingimmersive.com';

    public static function check(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $problems = [];

        $connection = config('database.default');
        $host = config("database.connections.$connection.host");
        if ($host !== null && ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $problems[] = "the database host is $host";
        }
        if (config('filesystems.disks.digitalocean.bucket') === self::LIVE_BUCKET) {
            $problems[] = 'photos would save to the live bucket';
        }
        foreach (['app.url', 'app.frontend_url'] as $key) {
            if (str_contains((string) config($key), self::LIVE_DOMAIN)) {
                $problems[] = "$key is the live site";
            }
        }
        if (! in_array(config('mail.default'), ['log', 'array'], true)) {
            $problems[] = 'mail would really send';
        }

        if ($problems) {
            throw new RuntimeException('This local copy points at live: '.implode('; ', $problems).'. Fix .env.');
        }
    }
}
