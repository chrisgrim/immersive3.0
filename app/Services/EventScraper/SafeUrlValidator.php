<?php

namespace App\Services\EventScraper;

/**
 * Blocks SSRF probes against private/internal IPs.
 *
 * Every URL that gets passed to the scraper's HTTP client must run through here
 * first. The validator rejects anything that isn't http(s) and any host that
 * resolves to a loopback / private / link-local / cloud-metadata IP.
 *
 * Note: this is a defense-in-depth check against careless or malicious input,
 * not a full DNS-rebinding mitigation. For paranoid scenarios, additionally
 * resolve the host yourself, pin the IP, and pass it to the HTTP client.
 */
class SafeUrlValidator
{
    public static function check(string $url): true
    {
        $parts = parse_url($url);

        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new UnsafeUrlException('URL is not parseable.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException("Scheme {$scheme} is not allowed.");
        }

        $host = strtolower($parts['host']);

        // Block obvious hostname shortcuts before DNS so a typo doesn't slip through.
        $blockedHosts = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'];
        if (in_array($host, $blockedHosts, true)) {
            throw new UnsafeUrlException("Host {$host} is not allowed.");
        }

        // If the host *is* an IP literal, check it directly. Otherwise resolve A
        // and AAAA records and check every address we get back.
        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : self::resolveAll($host);

        if (empty($ips)) {
            throw new UnsafeUrlException("Could not resolve host {$host}.");
        }

        foreach ($ips as $ip) {
            if (! self::ipIsPublic($ip)) {
                throw new UnsafeUrlException("Host {$host} resolves to a non-public address ({$ip}).");
            }
        }

        return true;
    }

    private static function resolveAll(string $host): array
    {
        $v4 = @gethostbynamel($host) ?: [];

        // dns_get_record for AAAA — wrapped in try/catch because it'll warn if
        // DNS is misconfigured locally.
        $v6 = [];
        try {
            $records = @dns_get_record($host, DNS_AAAA);
            if ($records) {
                $v6 = array_column($records, 'ipv6');
            }
        } catch (\Exception $e) {
            // Fall through with whatever we have.
        }

        return array_filter(array_merge($v4, $v6));
    }

    private static function ipIsPublic(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE blocks 10/8, 172.16/12, 192.168/16, fc00::/7.
        // FILTER_FLAG_NO_RES_RANGE blocks loopback, link-local, multicast, reserved.
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }
}
