<?php

use App\Services\EventScraper\SafeUrlValidator;
use App\Services\EventScraper\UnsafeUrlException;

/**
 * Regression coverage for H-S3 (SSRF in EventScraper).
 *
 * Note: tests that rely on DNS resolution (the `localhost` keyword, the
 * `127.0.0.1` literal, public hostnames) are stable; tests for private CIDR
 * ranges use literals so they're hermetic.
 */
test('accepts a plain https URL', function () {
    expect(SafeUrlValidator::check('https://example.com/event'))->toBeTrue();
});

test('rejects non-http(s) schemes', function () {
    SafeUrlValidator::check('file:///etc/passwd');
})->throws(UnsafeUrlException::class);

test('rejects ftp', function () {
    SafeUrlValidator::check('ftp://example.com');
})->throws(UnsafeUrlException::class);

test('rejects gopher://', function () {
    SafeUrlValidator::check('gopher://example.com');
})->throws(UnsafeUrlException::class);

test('rejects unparseable URLs', function () {
    SafeUrlValidator::check('not-a-url');
})->throws(UnsafeUrlException::class);

test('rejects literal localhost hostname', function () {
    SafeUrlValidator::check('http://localhost/admin');
})->throws(UnsafeUrlException::class);

test('rejects 127.0.0.1', function () {
    SafeUrlValidator::check('http://127.0.0.1:9200');
})->throws(UnsafeUrlException::class);

test('rejects AWS EC2 metadata IP (169.254.169.254)', function () {
    SafeUrlValidator::check('http://169.254.169.254/latest/meta-data/');
})->throws(UnsafeUrlException::class);

test('rejects 10/8 private CIDR', function () {
    SafeUrlValidator::check('http://10.0.0.5');
})->throws(UnsafeUrlException::class);

test('rejects 172.16/12 private CIDR', function () {
    SafeUrlValidator::check('http://172.16.0.1');
})->throws(UnsafeUrlException::class);

test('rejects 192.168/16 private CIDR', function () {
    SafeUrlValidator::check('http://192.168.1.1');
})->throws(UnsafeUrlException::class);

test('rejects 0.0.0.0', function () {
    SafeUrlValidator::check('http://0.0.0.0');
})->throws(UnsafeUrlException::class);

test('rejects IPv6 loopback literal', function () {
    SafeUrlValidator::check('http://[::1]/');
})->throws(UnsafeUrlException::class);
