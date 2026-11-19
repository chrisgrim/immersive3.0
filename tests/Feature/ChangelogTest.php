<?php

use App\Support\Changelog;

afterEach(fn () => Changelog::flush());

test('the changelog lists posts newest first and opens each one', function () {
    $posts = Changelog::all();
    expect($posts)->not->toBeEmpty();
    expect($posts->pluck('date')->map->timestamp->all())
        ->toBe($posts->pluck('date')->map->timestamp->sortDesc()->values()->all());

    $first = $posts->first();
    $this->withoutVite()->get('/changelog')->assertOk()->assertSee($first['title']);
    $this->withoutVite()->get('/changelog/'.$first['slug'])->assertOk()->assertSee($first['title']);
});

test('an unknown post is a 404', function () {
    $this->withoutVite()->get('/changelog/no-such-post')->assertNotFound();
});

test('a post can carry no raw HTML or script links', function () {
    $file = resource_path('changelog/2000-01-01-test-escape.md');
    file_put_contents($file, "---\ntitle: Escape test\n---\n<script>alert(1)</script>\n\n[x](javascript:alert(1))\n");
    try {
        Changelog::flush();
        $html = Changelog::find('test-escape')['html'];
        expect($html)->not->toContain('<script>')->not->toContain('javascript:');
    } finally {
        unlink($file);
    }
});
