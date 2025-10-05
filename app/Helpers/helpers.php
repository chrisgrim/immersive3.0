<?php

use Illuminate\Support\Str;

if (!function_exists('is_internal_url')) {
    /**
     * Determine if a URL is internal to this application
     *
     * @param string|null $url
     * @return bool
     */
    function is_internal_url(?string $url): bool
    {
        if (empty($url)) {
            return true;
        }

        // Relative URLs are always internal
        if (Str::startsWith($url, '/')) {
            return true;
        }

        // Parse the URL
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            // If we can't parse it, treat as internal for safety
            return true;
        }

        $urlHost = $parsedUrl['host'];
        $appHost = request()->getHost();

        // Check if it's the same domain or subdomain
        return $urlHost === $appHost ||
               Str::endsWith($urlHost, '.' . $appHost) ||
               Str::endsWith($appHost, '.' . $urlHost);
    }
}

if (!function_exists('get_url_security_attributes')) {
    /**
     * Get security attributes for a URL (target and rel)
     *
     * @param string|null $url
     * @return array
     */
    function get_url_security_attributes(?string $url): array
    {
        if (is_internal_url($url)) {
            return [
                'target' => null,
                'rel' => null,
            ];
        }

        return [
            'target' => '_blank',
            'rel' => 'noopener noreferrer nofollow',
        ];
    }
}

