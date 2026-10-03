<?php

namespace App\Support\Analytics;

use MaxMind\Db\Reader;
use Throwable;

/**
 * Country and network (ASN) of an IP, from the DB-IP Lite databases that
 * ei:analytics-geo-update keeps in analytics.geo_path. About 0.2 ms a
 * lookup. Missing databases or unknown IPs give null, never an error.
 */
class GeoLookup
{
    /** @var array<string, Reader|false> */
    private array $readers = [];

    public function country(string $ip): ?string
    {
        $code = $this->lookup('country', $ip)['country']['iso_code'] ?? null;

        return is_string($code) && strlen($code) === 2 ? $code : null;
    }

    public function asn(string $ip): ?int
    {
        $asn = $this->lookup('asn', $ip)['autonomous_system_number'] ?? null;

        return is_numeric($asn) ? (int) $asn : null;
    }

    /**
     * City and region (state, province) from DB-IP City Lite, when that file
     * is there; never coordinates.
     *
     * @return array{city: ?string, region: ?string}
     */
    public function place(string $ip): array
    {
        $record = $this->lookup('city', $ip);
        $name = fn ($names) => is_string($names['en'] ?? null) ? mb_substr($names['en'], 0, 64) : null;

        return [
            'city' => $name($record['city']['names'] ?? []),
            'region' => $name($record['subdivisions'][0]['names'] ?? []),
        ];
    }

    private function lookup(string $database, string $ip): ?array
    {
        if ($ip === '') {
            return null;
        }

        if (! isset($this->readers[$database])) {
            $file = config('analytics.geo_path')."/{$database}.mmdb";
            try {
                $this->readers[$database] = is_file($file) ? new Reader($file) : false;
            } catch (Throwable $e) {
                report($e);
                $this->readers[$database] = false;
            }
        }

        if ($this->readers[$database] === false) {
            return null;
        }

        try {
            $record = $this->readers[$database]->get($ip);

            return is_array($record) ? $record : null;
        } catch (Throwable) {
            return null; // not a valid IP
        }
    }
}
