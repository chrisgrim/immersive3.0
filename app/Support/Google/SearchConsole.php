<?php

namespace App\Support\Google;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Google Search Console's Search Analytics API, read only: what people
 * searched on Google, how often EI showed up (impressions), how often they
 * clicked, and at what position. Off unless config services.search_console
 * has both a readable key file and a property (configured()).
 *
 * Only the import command calls Google; pages and MCP tools read the
 * stored totals (search_console_daily), never the API.
 */
class SearchConsole
{
    public const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public const API = 'https://searchconsole.googleapis.com/webmasters/v3/sites/';

    /** Tries per request on a 429, a 5xx or a dropped connection. */
    public const ATTEMPTS = 5;

    private ?ServiceAccountToken $token = null;

    public static function configured(): bool
    {
        return self::siteUrl() !== null && self::credentialsPath() !== null;
    }

    public static function siteUrl(): ?string
    {
        $site = trim((string) config('services.search_console.site_url'));

        return $site === '' ? null : $site;
    }

    /** The key file's path (relative paths are from the app's root), when it can be read. */
    public static function credentialsPath(): ?string
    {
        $path = trim((string) config('services.search_console.credentials'));
        if ($path === '') {
            return null;
        }
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        return is_file($path) && is_readable($path) ? $path : null;
    }

    /**
     * The site's own host (sc-domain:everythingimmersive.com or
     * https://everythingimmersive.com/ both give everythingimmersive.com):
     * its pages are stored as paths.
     */
    public static function host(): ?string
    {
        $site = self::siteUrl();
        if ($site === null) {
            return null;
        }
        if (str_starts_with($site, 'sc-domain:')) {
            return strtolower(substr($site, 10));
        }

        $host = parse_url($site, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    /**
     * A page address as stored: for the site's own host or its www., the
     * path alone (no query string or fragment, no trailing slash except
     * "/"), so /events/x?ref=y adds up with /events/x; the whole address for
     * any other host (a subdomain on a domain property). Cut to 191
     * characters.
     */
    public static function pageKey(string $url): string
    {
        $parts = parse_url($url) ?: [];
        $host = strtolower($parts['host'] ?? '');
        $own = self::host();

        if ($own !== null && ($host === $own || $host === "www.{$own}")) {
            $url = rtrim($parts['path'] ?? '', '/');
            $url = $url === '' ? '/' : $url;
        }

        return mb_substr($url, 0, 191);
    }

    /**
     * One searchAnalytics/query call. Retries a 429 or 5xx (waiting as
     * Google asks, or longer each time), a dropped connection and a busy
     * token endpoint, and a stale token once; anything else throws a
     * SearchConsoleException whose fatal() says whether later calls would
     * fail the same way: no access, a wrong property or a refused key, or
     * Google still unavailable after every try.
     */
    public function query(array $body): array
    {
        $url = self::API.rawurlencode((string) self::siteUrl()).'/searchAnalytics/query';
        $refreshed = false;

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = Http::withToken($this->accessToken())
                    ->acceptJson()
                    ->timeout(60)
                    ->post($url, $body);
            } catch (ConnectionException|ServiceAccountTokenException $e) {
                if ($e instanceof ServiceAccountTokenException && ! $e->retryable()) {
                    throw new SearchConsoleException($e->getMessage(), true, $e);
                }
                if ($attempt >= self::ATTEMPTS) {
                    throw new SearchConsoleException('Google could not be reached after '.self::ATTEMPTS.' tries: '.$e->getMessage(), true, $e);
                }
                $this->wait($attempt, null);

                continue;
            }

            if ($response->successful()) {
                return $response->json('rows') ?? [];
            }

            if ($response->status() === 401 && ! $refreshed) {
                $this->token()->forget();
                $refreshed = true;

                continue;
            }

            $busy = $response->status() === 429 || $response->serverError();
            if ($busy && $attempt < self::ATTEMPTS) {
                $this->wait($attempt, $response);

                continue;
            }

            throw new SearchConsoleException(
                'Search Console answered HTTP '.$response->status().($busy ? ' after '.self::ATTEMPTS.' tries' : '').': '.mb_substr((string) $response->json('error.message', ''), 0, 300),
                $busy || in_array($response->status(), [401, 403, 404], true),
            );
        }
    }

    private function wait(int $attempt, ?Response $response): void
    {
        $asked = (int) ($response?->header('Retry-After') ?: 0);

        Sleep::for(min(60, max($asked, 2 ** $attempt)))->seconds();
    }

    /**
     * No readable key fails every call alike: fatal. A busy token endpoint
     * or a dropped connection is left to query() to retry.
     */
    private function accessToken(): string
    {
        try {
            return $this->token()->accessToken();
        } catch (ServiceAccountTokenException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw new SearchConsoleException($e->getMessage(), true, $e);
        }
    }

    private function token(): ServiceAccountToken
    {
        $path = self::credentialsPath() ?? throw new RuntimeException('Search Console is not configured.');

        return $this->token ??= ServiceAccountToken::fromFile($path, self::SCOPE);
    }
}
