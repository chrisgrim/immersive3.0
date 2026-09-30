<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * A local copy of the site runs against a copy of the live database, so it must
 * never be able to reach live: not its database, its photo bucket, its address,
 * or real people's inboxes (Chris, 2026-09-30: "nothing and I mean nothing can go
 * from our local to the prod server"). A wrong .env fails loudly at boot instead
 * of quietly touching live.
 *
 * It runs on local and testing, and on any Mac whatever APP_ENV says — the live
 * and dev servers are Linux, so APP_ENV=production on a laptop doesn't switch it
 * off. It checks where things really are, not only what .env claims: the
 * database must be THIS machine (a tunnel to the live DB on 127.0.0.1 passes a
 * host check, but reports the droplet's hostname), and photos may only go to the
 * test bucket (an allowlist, not a denylist).
 */
class LocalIsolation
{
    public const TEST_BUCKET = 'ei-test';

    public const LIVE_BUCKET = 'ei-prod';

    public const LIVE_DOMAIN = 'everythingimmersive.com';

    public static function applies(): bool
    {
        return static::appliesTo(app()->environment(), PHP_OS_FAMILY);
    }

    public static function appliesTo(string $env, string $os): bool
    {
        return in_array($env, ['local', 'testing'], true) || $os === 'Darwin';
    }

    public static function check(): void
    {
        if (! static::applies()) {
            return;
        }

        $problems = static::configProblems();
        if ($db = static::databaseProblem()) {
            $problems[] = $db;
        }

        if ($problems) {
            throw new RuntimeException('This local copy points at live: '.implode('; ', $problems).'. Fix .env.');
        }
    }

    /** What .env says, checked without touching the network. */
    public static function configProblems(): array
    {
        $problems = [];

        $connection = config('database.default');
        $host = config("database.connections.$connection.host");
        if ($host !== null && ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $problems[] = "the database host is $host";
        }
        $url = (string) config("database.connections.$connection.url");
        if ($url !== '' && ! in_array(parse_url($url, PHP_URL_HOST), ['127.0.0.1', 'localhost', '::1'], true)) {
            $problems[] = 'DB_URL points somewhere else';
        }

        foreach (['digitalocean', 's3'] as $disk) {
            $bucket = strtolower((string) config("filesystems.disks.$disk.bucket"));
            if ($bucket !== '' && $bucket !== self::TEST_BUCKET) {
                $problems[] = "photos on the $disk disk would save to $bucket, not ".self::TEST_BUCKET;
            }
            $endpoint = strtolower((string) config("filesystems.disks.$disk.endpoint"));
            if (str_contains($endpoint, self::LIVE_BUCKET)) {
                $problems[] = "the $disk endpoint names the live bucket";
            }
        }

        if (str_contains(strtolower((string) config('app.url')), self::LIVE_DOMAIN)) {
            $problems[] = 'APP_URL is the live site';
        }
        if (! in_array(config('mail.default'), ['log', 'array'], true)) {
            $problems[] = 'mail would really send';
        }

        return $problems;
    }

    /** The database server must be this machine. Null when it is (or can't be reached). */
    public static function databaseProblem(): ?string
    {
        if (config('database.connections.'.config('database.default').'.driver') !== 'mysql') {
            return null;
        }
        try {
            $server = DB::selectOne('select @@hostname as h')->h ?? null;
        } catch (Throwable) {
            return null; // no connection means nothing reaches anywhere; the app will say why
        }
        if ($server !== null && strcasecmp($server, gethostname()) !== 0) {
            return "the database is running on $server, not this machine";
        }

        return null;
    }
}
