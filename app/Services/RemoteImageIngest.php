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

        try {
            $response = Http::timeout(15)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'on_redirect' => function ($request, $response, $uri) {
                            ($this->urlValidator)((string) $uri);
                        },
                    ],
                ])
                ->withHeaders(['Accept' => 'image/*'])
                ->get($url);
        } catch (UnsafeUrlException $e) {
            throw new ImageIngestException('The URL redirected somewhere that is not allowed.');
        } catch (\Throwable $e) {
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

        $name = Str::of(parse_url($url, PHP_URL_PATH) ?? '')->basename()->whenEmpty(fn () => Str::of('image'));

        return new UploadedFile($path, $name.'.'.self::ALLOWED_MIMES[$mime], $mime, null, true);
    }
}
