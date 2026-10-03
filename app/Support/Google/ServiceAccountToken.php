<?php

namespace App\Support\Google;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * An OAuth access token for a Google service account, without Google's SDK:
 * a JWT signed with the account's private key (RS256, openssl_sign) is
 * traded at Google's token endpoint for a one-hour access token, which is
 * cached for 50 minutes. Errors never include the key.
 *
 * https://developers.google.com/identity/protocols/oauth2/service-account#httprest
 */
class ServiceAccountToken
{
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Google's tokens last an hour; this leaves room for clock drift. */
    private const CACHE_SECONDS = 3000;

    public function __construct(private string $clientEmail, private string $privateKey, private string $scope) {}

    public static function fromFile(string $path, string $scope): self
    {
        $json = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException("Not a readable service account key file: {$path}");
        }

        return new self($json['client_email'], $json['private_key'], $scope);
    }

    public function clientEmail(): string
    {
        return $this->clientEmail;
    }

    /** A cached access token, or a fresh one. */
    public function accessToken(): string
    {
        $cached = Cache::get($this->cacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->assertion(),
        ]);

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            // Google's error code and description only (e.g. invalid_grant).
            throw new RuntimeException('Google refused the service account token: HTTP '.$response->status().' '.mb_substr((string) ($response->json('error') ?? ''), 0, 60).' '.mb_substr((string) ($response->json('error_description') ?? ''), 0, 200));
        }

        $seconds = min(self::CACHE_SECONDS, max(60, (int) $response->json('expires_in', 3600) - 300));
        Cache::put($this->cacheKey(), $token, $seconds);

        return $token;
    }

    /** Drops the cached token (Google said it is no longer valid). */
    public function forget(): void
    {
        Cache::forget($this->cacheKey());
    }

    /** The signed JWT sent to the token endpoint. */
    public function assertion(?int $now = null): string
    {
        $now ??= time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $this->clientEmail,
            'scope' => $this->scope,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $unsigned = self::base64Url(json_encode($header)).'.'.self::base64Url(json_encode($claims));

        $key = openssl_pkey_get_private($this->privateKey);
        if ($key === false || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign with the service account key.');
        }

        return $unsigned.'.'.self::base64Url($signature);
    }

    public static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function cacheKey(): string
    {
        return 'google:token:'.md5($this->clientEmail.'|'.$this->scope);
    }
}
