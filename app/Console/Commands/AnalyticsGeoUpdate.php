<?php

namespace App\Console\Commands;

use App\Support\Analytics\Analytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Keeps the DB-IP Lite country and ASN databases in analytics.geo_path
 * fresh (they publish monthly). Runs daily but only downloads when a file
 * is missing or more than 20 days old. A new file replaces the old one only
 * after it has opened as a valid database. Data: IP Geolocation by DB-IP
 * (https://db-ip.com), CC BY 4.0.
 */
class AnalyticsGeoUpdate extends Command
{
    protected $signature = 'ei:analytics-geo-update {--force : Download even if the files are recent}';

    protected $description = 'Download the free DB-IP country and ASN databases used to flag bots.';

    public const FILES = ['country' => 'dbip-country-lite', 'asn' => 'dbip-asn-lite', 'city' => 'dbip-city-lite'];

    public function handle(): int
    {
        $dir = config('analytics.geo_path');
        File::ensureDirectoryExists($dir);

        foreach (self::FILES as $name => $remote) {
            // City Lite is ~120 MB: only fetched while city capture is on.
            if ($name === 'city' && ! Analytics::captures('city')) {
                continue;
            }

            $target = "{$dir}/{$name}.mmdb";

            if (! $this->option('force') && File::exists($target) && File::lastModified($target) > now()->subDays(20)->getTimestamp()) {
                continue;
            }

            // This month's file appears a few days in; fall back to last month's.
            foreach ([now(), now()->subMonthNoOverflow()] as $month) {
                if ($this->download("https://download.db-ip.com/free/{$remote}-{$month->format('Y-m')}.mmdb.gz", $target)) {
                    $this->info("Updated {$name}.mmdb ({$month->format('Y-m')}).");
                    break;
                }
            }
        }

        return self::SUCCESS;
    }

    private function download(string $url, string $target): bool
    {
        $gz = "{$target}.download.gz";
        $tmp = "{$target}.download";

        try {
            $response = Http::timeout(600)->sink($gz)->get($url);
            if (! $response->successful()) {
                return false;
            }

            $this->gunzip($gz, $tmp);
            (new Reader($tmp))->close(); // throws if it is not a valid database
            File::move($tmp, $target);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        } finally {
            File::delete([$gz, $tmp]);
        }
    }

    /**
     * Unzips a piece at a time: the city database is ~130 MB unzipped, more
     * than a CLI memory limit may allow in one string.
     */
    private function gunzip(string $from, string $to): void
    {
        $in = gzopen($from, 'rb') ?: throw new \RuntimeException("Could not open {$from}");
        $out = fopen($to, 'wb') ?: throw new \RuntimeException("Could not write {$to}");
        try {
            while (! gzeof($in)) {
                $chunk = gzread($in, 1024 * 1024);
                if ($chunk === false) {
                    throw new \RuntimeException("Could not unzip {$from}");
                }
                fwrite($out, $chunk);
            }
        } finally {
            gzclose($in);
            fclose($out);
        }
    }
}
