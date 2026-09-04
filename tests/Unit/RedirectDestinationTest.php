<?php

use App\Support\RedirectDestination;

test('a loopback or private-scheme redirect stays on this computer', function (string $uri) {
    expect(RedirectDestination::host($uri))->toBeNull();
})->with([
    'Claude Code' => 'http://localhost:3118/callback',
    'upper-case host' => 'http://LOCALHOST/cb',
    'subdomain of localhost' => 'http://app.localhost/cb',
    'IPv4 loopback' => 'http://127.0.0.1:8/cb',
    'elsewhere in 127/8' => 'http://127.5.5.5/cb',
    'IPv6 loopback' => 'http://[::1]:5555/cb',
    'Cursor' => 'cursor://anysphere.cursor-retrieval/oauth/callback',
    'VS Code' => 'vscode-insiders://callback',
    'not a URL' => 'garbage',
    'empty' => '',
]);

test('a hosted redirect names its host', function (string $uri, string $host) {
    expect(RedirectDestination::host($uri))->toBe($host);
})->with([
    ['https://claude.ai/api/mcp/auth_callback', 'claude.ai'],
    ['https://ChatGPT.com/connector_platform_oauth_redirect', 'chatgpt.com'],
    // "localhost" here is the userinfo; the real host is what gets named.
    ['http://localhost:80@evil.example/cb', 'evil.example'],
    ['https://127.0.0.1.evil.example/cb', '127.0.0.1.evil.example'],
]);
