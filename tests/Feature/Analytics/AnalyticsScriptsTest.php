<?php

/**
 * Google Analytics and Umami each load only while their id is set, and the
 * privacy page names only the ones that are on. The dead Tag Manager
 * snippet is gone.
 */
test('Google Analytics loads only while ANALYTICS_ID is set', function () {
    config(['services.analytics.id' => 'G-TEST123', 'services.umami.website_id' => null]);
    $html = $this->withoutVite()->get('/privacy')->assertOk()->getContent();

    expect($html)->toContain('gtag/js?id=G-TEST123')
        ->toContain('We also use Google Analytics')
        ->not->toContain('cloud.umami.is')
        ->not->toContain('GTM-5LHWVRN');

    config(['services.analytics.id' => null]);
    $html = $this->withoutVite()->get('/privacy')->assertOk()->getContent();

    expect($html)->not->toContain('googletagmanager')->not->toContain('We also use Google Analytics');
});

test('Umami loads only while UMAMI_WEBSITE_ID is set, and only counts the live site', function () {
    config(['services.analytics.id' => null, 'services.umami.website_id' => 'abc-123']);
    $html = $this->withoutVite()->get('/privacy')->assertOk()->getContent();

    expect($html)->toContain('data-website-id="abc-123"')
        ->toContain('data-domains="everythingimmersive.com"')
        ->toContain('We also use Umami');
});

test('the privacy page describes the cookieless counts and drops what was never true', function () {
    $html = $this->withoutVite()->get('/privacy')->assertOk()->getContent();

    expect($html)->toContain('Our own visit counts')
        ->toContain('13 months')
        ->not->toContain('Payment information')
        ->not->toContain('1234 Experience Street')
        ->not->toContain('purchase tickets');
});
