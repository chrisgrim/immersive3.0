<?php

namespace App\Services;

use App\Services\EventScraper\SafeUrlValidator;
use App\Services\EventScraper\UnsafeUrlException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Downloads an image from a URL so it can flow through the existing
 * ImageHandler exactly like a browser upload.
 *
 * Defenses: SafeUrlValidator on the initial URL AND on every redirect hop
 * (closes the redirect-SSRF gap), a byte-size cap, and finfo sniffing of the
 * actual bytes (never the extension or the server's Content-Type header).
 *
 * The returned UploadedFile points at a temp file — callers are responsible
 * for unlinking it (wrap usage in try/finally).
 */
class RemoteImageIngest
{
    protected const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Many image CDNs reject requests that lack a browser-like User-Agent
     * (hotlink protection), answering 403. Users routinely paste URLs from
     * exactly those hosts, so we present a normal browser UA. This does not
     * weaken any defense: SafeUrlValidator still runs on the initial URL and
     * every redirect hop, the byte cap still applies, and the bytes are still
     * finfo-sniffed rather than trusted.
     */
    protected const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * @param  (callable(string): void)|null  $urlValidator  Overridable so tests
     *                                                       can skip real DNS resolution; production always uses SafeUrlValidator.
     */
    public function __construct(protected mixed $urlValidator = null)
    {
        $this->urlValidator ??= fn (string $url) => SafeUrlValidator::check($url);
    }

    public function fetch(string $url, int $maxBytes): UploadedFile
    {
        try {
            ($this->urlValidator)($url);
        } catch (UnsafeUrlException $e) {
            throw new ImageIngestException('That URL is not allowed: '.$e->getMessage());
        }

        // Abort mid-transfer once the byte cap is exceeded — a hostile server
        // must not be able to stream an oversized body into worker memory.
        $tooLarge = false;

        try {
            $response = Http::timeout(15)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'on_redirect' => function ($request, $response, $uri) {
                            ($this->urlValidator)((string) $uri);
                        },
                    ],
                    'progress' => function ($downloadTotal, $downloadedBytes) use ($maxBytes, &$tooLarge) {
                        if ($downloadTotal > $maxBytes || $downloadedBytes > $maxBytes) {
                            $tooLarge = true;
                            throw new ImageIngestException('too large');
                        }
                    },
                ])
                ->withHeaders([
                    'Accept' => 'image/*',
                    'User-Agent' => self::USER_AGENT,
                ])
                ->get($url);
        } catch (UnsafeUrlException $e) {
            throw new ImageIngestException('The URL redirected somewhere that is not allowed.');
        } catch (\Throwable $e) {
            if ($tooLarge) {
                $maxMb = round($maxBytes / 1048576, 1);
                throw new ImageIngestException("The image is too large (max {$maxMb} MB).");
            }
            throw new ImageIngestException('Could not download the image: '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new ImageIngestException("The image URL returned HTTP {$response->status()}.");
        }

        $bytes = $response->body();

        if (strlen($bytes) === 0) {
            throw new ImageIngestException('The URL returned an empty response.');
        }

        if (strlen($bytes) > $maxBytes) {
            $maxMb = round($maxBytes / 1048576, 1);
            throw new ImageIngestException("The image is too large (max {$maxMb} MB).");
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'unknown';

        if (! array_key_exists($mime, self::ALLOWED_MIMES)) {
            throw new ImageIngestException("The URL did not return a supported image (got {$mime}; allowed: jpeg, png, webp).");
        }

        $path = tempnam(sys_get_temp_dir(), 'mcp-img-');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            throw new ImageIngestException('Could not store the downloaded image.');
        }

        // Verify the file actually decodes before any caller deletes an
        // existing image to make room for it — finfo only checks the header.
        try {
            \Intervention\Image\Laravel\Facades\Image::read($path);
        } catch (\Throwable $e) {
            @unlink($path);
            throw new ImageIngestException('The file looks like an image but could not be decoded (corrupt or truncated).');
        }

        $name = Str::of(parse_url($url, PHP_URL_PATH) ?? '')->basename()->whenEmpty(fn () => Str::of('image'));

        return new UploadedFile($path, $name.'.'.self::ALLOWED_MIMES[$mime], $mime, null, true);
    }
}
