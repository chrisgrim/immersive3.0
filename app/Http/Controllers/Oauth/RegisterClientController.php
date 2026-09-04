<?php

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;

/**
 * OAuth 2.0 dynamic client registration (RFC 7591) for MCP clients — the
 * step where an assistant tells this server where to send the browser
 * after consent. That redirect is the one place a malicious "client" could
 * turn an approved authorization into a stolen code, so it is judged on the
 * parsed URL, not on a prefix.
 *
 * Replaces laravel/mcp's OAuthRegisterController, whose loopback rule was
 * `starts_with("http://localhost:")` — satisfied by
 * `http://localhost:80@evil.example/callback`, whose real host is evil.example.
 *
 * Rules, all from config/mcp.php:
 *  - http/https: no userinfo, no fragment; the host is a loopback name (any
 *    port, since CLIs pick one at random), or exactly one of the allowlisted
 *    origins (scheme and host both, no port);
 *  - a private scheme (cursor://…): listed in custom_schemes, has a host, no
 *    userinfo — a scheme is handled by an app on the user's own machine.
 * Every client is public (PKCE, no secret), authorization-code only.
 */
class RegisterClientController extends Controller
{
    public const MAX_REDIRECT_URIS = 5;

    public const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    public function __invoke(Request $request, ClientRepository $clients): JsonResponse
    {
        try {
            $validated = $request->validate([
                'client_name' => ['nullable', 'string', 'min:1', 'max:100'],
                'name' => ['nullable', 'string', 'min:1', 'max:100'],
                'redirect_uris' => ['required', 'array', 'min:1', 'max:'.self::MAX_REDIRECT_URIS],
                'redirect_uris.*' => ['required', 'string', 'max:512', function (string $attribute, mixed $value, \Closure $fail): void {
                    if (($reason = $this->rejectRedirect((string) $value)) !== null) {
                        $fail("{$attribute}: {$reason}");
                    }
                }],
            ]);
        } catch (ValidationException $e) {
            $redirectError = collect($e->errors())->keys()->contains(fn ($key) => str_starts_with($key, 'redirect_uris'));

            return response()->json([
                'error' => $redirectError ? 'invalid_redirect_uri' : 'invalid_client_metadata',
                'error_description' => collect($e->errors())->flatten()->first(),
            ], 400);
        }

        $client = $clients->createAuthorizationCodeGrantClient(
            name: $this->clientName($validated),
            redirectUris: array_values(array_unique($validated['redirect_uris'])),
            confidential: false,
            enableDeviceFlow: false,
        );

        return response()->json([
            'client_id' => (string) $client->getKey(),
            'client_name' => $client->name,
            'grant_types' => $client->grant_types,
            'response_types' => ['code'],
            'redirect_uris' => $client->redirect_uris,
            'scope' => 'mcp:use',
            'token_endpoint_auth_method' => 'none',
        ], 201);
    }

    /**
     * Why a redirect URI is refused, or null if it is acceptable.
     */
    public function rejectRedirect(string $uri): ?string
    {
        if (preg_match('/[\x00-\x20\x7f]/', $uri)) {
            return 'contains whitespace or control characters.';
        }

        $parts = parse_url($uri);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return 'is not an absolute URL with a host.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'must not contain credentials.';
        }

        if (isset($parts['fragment'])) {
            return 'must not contain a fragment.';
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (in_array($scheme, ['http', 'https'], true)) {
            if (in_array($host, self::LOOPBACK_HOSTS, true)) {
                return null;
            }

            if (isset($parts['port'])) {
                return 'must not specify a port (loopback addresses excepted).';
            }

            $allowed = array_map(fn ($origin) => strtolower(rtrim((string) $origin, '/')), config('mcp.redirect_domains', []));

            return in_array("{$scheme}://{$host}", $allowed, true)
                ? null
                : 'is not a permitted redirect origin.';
        }

        $schemes = array_map('strtolower', config('mcp.custom_schemes', []));

        return in_array($scheme, $schemes, true) ? null : 'uses a scheme that is not permitted.';
    }

    protected function clientName(array $validated): string
    {
        $name = trim(preg_replace('/[^\P{C}]+/u', '', (string) ($validated['client_name'] ?? $validated['name'] ?? '')));

        return $name !== '' ? Str::limit($name, 100, '') : 'MCP client';
    }
}
