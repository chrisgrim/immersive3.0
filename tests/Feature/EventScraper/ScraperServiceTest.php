<?php

use App\Services\EventScraper\EventScraperService;
use App\Services\EventScraper\ScrapedEventData;
use App\Services\EventScraper\Scrapers\GenericAIScraper;
use App\Services\EventScraper\Scrapers\ScraperInterface;
use Illuminate\Support\Facades\Http;

// note: GenericAIScraper picks its AI provider in the constructor based on
// whichever API key (anthropic preferred, else openai) is configured in the
// environment. To stay environment-independent we Http::fake BOTH providers
// with their respective response shapes so either selection works. Public page
// fetches are caught by the `*` fallthrough. The SafeUrlValidator that runs
// inside fetchPage is already covered by its own test, so we use real public
// IPs (8.8.8.8 / 1.1.1.1) to sail through it without a DNS lookup.

function fakeScraperHttp(array $extracted = ['name' => 'Faked Event', 'name_confidence' => 'high'], string $pageHtml = '<html><body><h1>Page</h1></body></html>'): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => json_encode($extracted)]],
        ], 200),
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($extracted)]]],
        ], 200),
        '*' => Http::response($pageHtml, 200),
    ]);
}

/**
 * Reach a private method on a given object instance.
 */
function callPrivate(object $instance, string $method, array $args): mixed
{
    $ref = new ReflectionMethod($instance, $method);
    $ref->setAccessible(true);

    return $ref->invoke($instance, ...$args);
}

// ----- normalizeUrl() (private, via reflection) -----

test('scrape normalizes a bare URL by prepending https', function () {
    $svc = new EventScraperService;

    expect(callPrivate($svc, 'normalizeUrl', ['example.com/foo']))->toBe('https://example.com/foo');
});

test('normalizeUrl leaves an existing http(s) scheme untouched and trims whitespace', function () {
    $svc = new EventScraperService;

    expect(callPrivate($svc, 'normalizeUrl', ['http://example.com']))->toBe('http://example.com');
    expect(callPrivate($svc, 'normalizeUrl', ['  https://example.com  ']))->toBe('https://example.com');
    expect(callPrivate($svc, 'normalizeUrl', ['  example.com  ']))->toBe('https://example.com');
});

test('scrape stores the normalized URL as the sourceUrl', function () {
    fakeScraperHttp();
    $svc = new EventScraperService;

    $result = $svc->scrape('8.8.8.8/events');

    expect($result->sourceUrl)->toBe('https://8.8.8.8/events');
});

// ----- scraper selection by priority -----

test('scrape selects the highest-priority scraper that can handle the URL', function () {
    fakeScraperHttp();
    $svc = new EventScraperService;

    // A specialized scraper with priority above GenericAIScraper(0).
    $high = new class implements ScraperInterface
    {
        public function canHandle(string $url): bool
        {
            return true;
        }

        public function getPriority(): int
        {
            return 100;
        }

        public function scrape(string $url): ScrapedEventData
        {
            return new ScrapedEventData(name: 'From High Scraper', scraperUsed: 'high_priority');
        }
    };

    $svc->registerScraper($high);

    $result = $svc->scrape('8.8.8.8/foo');

    // The high-priority scraper wins over the default GenericAIScraper.
    expect($result->scraperUsed)->toBe('high_priority');
    expect($result->name)->toBe('From High Scraper');
});

test('scrape falls back to the GenericAIScraper when a higher-priority scraper cannot handle the URL', function () {
    fakeScraperHttp(['name' => 'Generic Result', 'name_confidence' => 'high']);
    $svc = new EventScraperService;

    $cannotHandle = new class implements ScraperInterface
    {
        public function canHandle(string $url): bool
        {
            return false;
        }

        public function getPriority(): int
        {
            return 100;
        }

        public function scrape(string $url): ScrapedEventData
        {
            return new ScrapedEventData(name: 'Should not be used');
        }
    };

    $svc->registerScraper($cannotHandle);

    $result = $svc->scrape('8.8.8.8/foo');

    expect($result->scraperUsed)->toBe('generic_ai');
    expect($result->name)->toBe('Generic Result');
});

test('scrape continues to the next scraper when a higher-priority scraper throws', function () {
    fakeScraperHttp(['name' => 'Recovered via generic', 'name_confidence' => 'high']);
    $svc = new EventScraperService;

    $throws = new class implements ScraperInterface
    {
        public function canHandle(string $url): bool
        {
            return true;
        }

        public function getPriority(): int
        {
            return 100;
        }

        public function scrape(string $url): ScrapedEventData
        {
            throw new \RuntimeException('boom');
        }
    };

    $svc->registerScraper($throws);

    $result = $svc->scrape('8.8.8.8/foo');

    // Exception is swallowed and the loop moves on to GenericAIScraper.
    expect($result->scraperUsed)->toBe('generic_ai');
    expect($result->name)->toBe('Recovered via generic');
});

// ----- mergeResults() (private, via reflection) -----

test('mergeResults returns a single result unchanged without collecting additional URLs', function () {
    $svc = new EventScraperService;

    $only = new ScrapedEventData(name: 'Solo', sourceUrl: 'https://a.com', additionalUrls: ['https://social.com']);

    $merged = callPrivate($svc, 'mergeResults', [[$only]]);

    expect($merged->name)->toBe('Solo');
    // note: the count===1 short-circuit returns the object verbatim, so its own
    // sourceUrl is NOT appended to additionalUrls.
    expect($merged->additionalUrls)->toBe(['https://social.com']);
});

test('mergeResults prefers higher-confidence scalar values', function () {
    $svc = new EventScraperService;

    $low = new ScrapedEventData(name: 'Low Name', nameConfidence: 'low', sourceUrl: 'https://a.com');
    $high = new ScrapedEventData(name: 'High Name', nameConfidence: 'high', sourceUrl: 'https://b.com');

    $merged = callPrivate($svc, 'mergeResults', [[$low, $high]]);

    expect($merged->name)->toBe('High Name');
    expect($merged->nameConfidence)->toBe('high');
});

test('mergeResults keeps the base value when a later result has lower confidence', function () {
    $svc = new EventScraperService;

    $high = new ScrapedEventData(name: 'High Name', nameConfidence: 'high', sourceUrl: 'https://a.com');
    $low = new ScrapedEventData(name: 'Low Name', nameConfidence: 'low', sourceUrl: 'https://b.com');

    $merged = callPrivate($svc, 'mergeResults', [[$high, $low]]);

    expect($merged->name)->toBe('High Name');
    expect($merged->nameConfidence)->toBe('high');
});

test('mergeResults fills an empty base field from a later result regardless of confidence', function () {
    $svc = new EventScraperService;

    // Base has no description at all; later result supplies one with null confidence.
    $base = new ScrapedEventData(name: 'Event', sourceUrl: 'https://a.com');
    $other = new ScrapedEventData(description: 'Filled in later', sourceUrl: 'https://b.com');

    $merged = callPrivate($svc, 'mergeResults', [[$base, $other]]);

    expect($merged->description)->toBe('Filled in later');
});

test('mergeResults merges and dedupes array fields', function () {
    $svc = new EventScraperService;

    $a = new ScrapedEventData(tags: ['x', 'y'], sourceUrl: 'https://a.com');
    $b = new ScrapedEventData(tags: ['y', 'z'], sourceUrl: 'https://b.com');

    $merged = callPrivate($svc, 'mergeResults', [[$a, $b]]);

    expect($merged->tags)->toBe(['x', 'y', 'z']);
});

test('mergeResults collects each later result sourceUrl into additionalUrls', function () {
    $svc = new EventScraperService;

    $a = new ScrapedEventData(name: 'Event', sourceUrl: 'https://main.com', additionalUrls: ['https://social.com']);
    $b = new ScrapedEventData(sourceUrl: 'https://tickets.com');
    $c = new ScrapedEventData(sourceUrl: 'https://blog.com');

    $merged = callPrivate($svc, 'mergeResults', [[$a, $b, $c]]);

    // Base's pre-existing additionalUrls is preserved, plus each later sourceUrl.
    expect($merged->additionalUrls)->toBe(['https://social.com', 'https://tickets.com', 'https://blog.com']);
});

test('mergeResults returns an empty DTO for an empty input array', function () {
    $svc = new EventScraperService;

    $merged = callPrivate($svc, 'mergeResults', [[]]);

    expect($merged)->toBeInstanceOf(ScrapedEventData::class);
    expect($merged->name)->toBeNull();
});

test('scrapeMultiple scrapes each URL and merges the results', function () {
    fakeScraperHttp(['name' => 'Merged Event', 'name_confidence' => 'high']);
    $svc = new EventScraperService;

    $merged = $svc->scrapeMultiple(['8.8.8.8/a', '1.1.1.1/b']);

    expect($merged->name)->toBe('Merged Event');
    // Second URL's (normalized) sourceUrl is collected.
    expect($merged->additionalUrls)->toContain('https://1.1.1.1/b');
});

// ----- GenericAIScraper::extractImages() (private, via reflection) -----

test('extractImages parses img src, og:image and twitter:image', function () {
    $scraper = new GenericAIScraper;

    $html = <<<'HTML'
    <html><head>
    <meta property="og:image" content="https://cdn.example.com/hero.jpg">
    <meta name="twitter:image" content="https://cdn.example.com/twitter-card.png">
    </head><body>
    <img src="https://cdn.example.com/photo1.jpg">
    </body></html>
    HTML;

    $images = callPrivate($scraper, 'extractImages', [$html, 'https://example.com/events/show']);

    expect($images)->toContain('https://cdn.example.com/hero.jpg');
    expect($images)->toContain('https://cdn.example.com/twitter-card.png');
    expect($images)->toContain('https://cdn.example.com/photo1.jpg');
});

test('extractImages filters out icons, logos, tracking pixels and tiny images', function () {
    $scraper = new GenericAIScraper;

    $html = <<<'HTML'
    <img src="https://cdn.example.com/good-photo.jpg">
    <img src="https://cdn.example.com/site-logo.png">
    <img src="https://cdn.example.com/favicon.ico">
    <img src="https://cdn.example.com/anim.gif">
    <img src="https://cdn.example.com/tracking-pixel.png">
    <img src="https://cdn.example.com/icon-share.png">
    <img src="https://cdn.example.com/thumb-50x50.jpg">
    HTML;

    $images = callPrivate($scraper, 'extractImages', [$html, 'https://example.com/']);

    expect($images)->toContain('https://cdn.example.com/good-photo.jpg');
    expect($images)->not->toContain('https://cdn.example.com/site-logo.png');
    expect($images)->not->toContain('https://cdn.example.com/favicon.ico');
    expect($images)->not->toContain('https://cdn.example.com/anim.gif');
    expect($images)->not->toContain('https://cdn.example.com/tracking-pixel.png');
    expect($images)->not->toContain('https://cdn.example.com/icon-share.png');
    // note: "50x50" matches the NxN guard and 50 < 100, so it's dropped.
    expect($images)->not->toContain('https://cdn.example.com/thumb-50x50.jpg');
});

test('extractImages resolves protocol-relative, root-relative, relative and absolute URLs against the base', function () {
    $scraper = new GenericAIScraper;

    $html = <<<'HTML'
    <img src="//cdn.example.com/protocol-relative.jpg">
    <img src="/root-relative.jpg">
    <img src="relative.jpg">
    <img src="https://other.com/absolute.jpg">
    HTML;

    $images = callPrivate($scraper, 'extractImages', [$html, 'https://example.com/events/show']);

    // protocol-relative -> prefixed with https:
    expect($images)->toContain('https://cdn.example.com/protocol-relative.jpg');
    // root-relative -> scheme + host
    expect($images)->toContain('https://example.com/root-relative.jpg');
    // relative -> resolved against dirname of the base URL
    expect($images)->toContain('https://example.com/events/relative.jpg');
    // absolute -> untouched
    expect($images)->toContain('https://other.com/absolute.jpg');
});

test('extractImages prepends meta images ahead of inline img tags', function () {
    $scraper = new GenericAIScraper;

    $html = <<<'HTML'
    <html><head>
    <meta property="og:image" content="https://cdn.example.com/og.jpg">
    <meta name="twitter:image" content="https://cdn.example.com/twitter.jpg">
    </head><body>
    <img src="https://cdn.example.com/inline.jpg">
    </body></html>
    HTML;

    $images = callPrivate($scraper, 'extractImages', [$html, 'https://example.com/']);

    // note: twitter:image is prepended last, so it lands first; og:image next,
    // then inline <img> sources keep document order at the tail.
    expect($images)->toBe([
        'https://cdn.example.com/twitter.jpg',
        'https://cdn.example.com/og.jpg',
        'https://cdn.example.com/inline.jpg',
    ]);
});

test('extractImages limits the result to 10 images', function () {
    $scraper = new GenericAIScraper;

    $html = '';
    for ($i = 0; $i < 15; $i++) {
        $html .= "<img src=\"https://cdn.example.com/photo-{$i}.jpg\">";
    }

    $images = callPrivate($scraper, 'extractImages', [$html, 'https://example.com/']);

    expect($images)->toHaveCount(10);
});

test('extractImages dedupes repeated sources', function () {
    $scraper = new GenericAIScraper;

    $html = <<<'HTML'
    <img src="https://cdn.example.com/dupe.jpg">
    <img src="https://cdn.example.com/dupe.jpg">
    <img src="https://cdn.example.com/unique.jpg">
    HTML;

    $images = callPrivate($scraper, 'extractImages', [$html, 'https://example.com/']);

    expect($images)->toBe([
        'https://cdn.example.com/dupe.jpg',
        'https://cdn.example.com/unique.jpg',
    ]);
});

// ----- GenericAIScraper::cleanHtml() (private, via reflection) -----

test('cleanHtml strips script and style blocks and HTML comments', function () {
    $scraper = new GenericAIScraper;

    $html = '<html><head><style>.x{color:red}</style>'
        .'<script>alert("xss")</script></head>'
        .'<body><!-- hidden comment --><h1>Title</h1><p>Body text</p></body></html>';

    $cleaned = callPrivate($scraper, 'cleanHtml', [$html]);

    expect($cleaned)->not->toContain('alert');
    expect($cleaned)->not->toContain('color:red');
    expect($cleaned)->not->toContain('hidden comment');
    expect($cleaned)->toContain('Title');
    expect($cleaned)->toContain('Body text');
    // All tags are stripped after structural newlines are inserted.
    expect($cleaned)->not->toContain('<');
});

test('cleanHtml truncates content beyond ~15k characters', function () {
    $scraper = new GenericAIScraper;

    $html = '<p>'.str_repeat('word ', 5000).'</p>';

    $cleaned = callPrivate($scraper, 'cleanHtml', [$html]);

    expect($cleaned)->toContain('[Content truncated...]');
    // 15000 chars + the appended "\n[Content truncated...]" marker (22 chars).
    expect(strlen($cleaned))->toBe(15022);
});

test('cleanHtml leaves short content untouched by the truncation marker', function () {
    $scraper = new GenericAIScraper;

    $cleaned = callPrivate($scraper, 'cleanHtml', ['<p>Just a little content here.</p>']);

    expect($cleaned)->not->toContain('[Content truncated...]');
    expect($cleaned)->toContain('Just a little content here.');
});

// ----- scrape() failure path -----

test('scrape returns a generic_ai result with a failure note when the page fetch fails', function () {
    Http::fake([
        '*' => Http::response('Not found', 404),
    ]);

    $svc = new EventScraperService;
    $result = $svc->scrape('8.8.8.8/missing');

    // note: a non-2xx fetch makes fetchPage() return null, and the scraper
    // short-circuits to a near-empty DTO rather than calling the AI.
    expect($result->scraperUsed)->toBe('generic_ai');
    expect($result->rawNotes)->toContain('Failed to fetch page content');
    expect($result->name)->toBeNull();
});
