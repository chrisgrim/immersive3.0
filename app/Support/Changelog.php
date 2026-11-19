<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The changelog: what's new on Everything Immersive, one Markdown file per
 * post in resources/changelog, named YYYY-MM-DD-the-slug.md. Posts are added
 * by hand (asked of Claude) and go live with a deploy; there is no editor.
 *
 * A file starts with a header of `key: value` lines between two `---` lines:
 *
 *     ---
 *     title: Search a whole country
 *     summary: One line for the card and the page description.
 *     image: /images/changelog/countries.jpg   (optional)
 *     image_alt: The map zoomed out over France  (optional)
 *     takeaways: First point | Second point  (optional, "Key takeaways")
 *     ---
 *     The post, in Markdown.
 *
 * A picture on a line of its own (![what it shows](/images/changelog/x.jpg))
 * becomes a figure, its description printed under it as the caption.
 *
 * The date comes from the file name, so the newest post sorts first.
 */
class Changelog
{
    private const PATTERN = '/^(\d{4}-\d{2}-\d{2})-([a-z0-9-]+)\.md$/';

    /** @var Collection<int, array<string, mixed>>|null */
    private static ?Collection $posts = null;

    /** Every post, newest first. */
    public static function all(): Collection
    {
        return self::$posts ??= collect(glob(resource_path('changelog/*.md')) ?: [])
            ->map(fn (string $path) => self::read($path))
            ->filter()
            ->sortByDesc(fn (array $post) => $post['date']->format('Y-m-d').'|'.$post['slug'])
            ->values();
    }

    public static function find(string $slug): ?array
    {
        return self::all()->firstWhere('slug', $slug);
    }

    /** For tests: read the folder again. */
    public static function flush(): void
    {
        self::$posts = null;
    }

    /** A picture alone in its paragraph: a figure, captioned with its (already escaped) description. */
    private static function figures(string $html): string
    {
        return preg_replace(
            '#<p>\s*<img src="([^"]*)" alt="([^"]*)"\s*/?>\s*</p>#',
            '<figure><img src="$1" alt="$2" loading="lazy"><figcaption>$2</figcaption></figure>',
            $html
        ) ?? $html;
    }

    private static function read(string $path): ?array
    {
        if (! preg_match(self::PATTERN, basename($path), $name)) {
            return null;
        }

        $text = (string) file_get_contents($path);
        $meta = [];
        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $text, $parts)) {
            foreach (preg_split('/\R/', $parts[1]) as $line) {
                if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $pair)) {
                    $meta[$pair[1]] = trim($pair[2]);
                }
            }
            $text = $parts[2];
        }

        if (blank($meta['title'] ?? null)) {
            return null;
        }

        return [
            'slug' => $name[2],
            'date' => Carbon::parse($name[1]),
            'title' => $meta['title'],
            'summary' => $meta['summary'] ?? null,
            'image' => filled($meta['image'] ?? null) ? $meta['image'] : null,
            'image_alt' => $meta['image_alt'] ?? '',
            'takeaways' => array_values(array_filter(array_map('trim', explode('|', $meta['takeaways'] ?? '')))),
            // Our own words, but still no raw HTML or javascript: links.
            'html' => self::figures(Str::markdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false])),
        ];
    }
}
