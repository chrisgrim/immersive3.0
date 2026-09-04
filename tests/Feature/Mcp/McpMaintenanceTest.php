<?php

use App\Models\User;
use Illuminate\Contracts\Foundation\MaintenanceMode;

/**
 * None of the MCP or OAuth routes is in the maintenance-mode allow-list
 * (bootstrap/app.php): while the site is down, assistants are down too.
 */
test('the mcp and oauth endpoints all answer 503 during maintenance', function () {
    $token = mcpToken(User::factory()->create());
    app(MaintenanceMode::class)->activate(['status' => 503, 'time' => now()->getTimestamp()]);

    try {
        mcpCall($this, $token)->assertStatus(503);
        $this->getJson('/.well-known/oauth-authorization-server')->assertStatus(503);
        $this->postJson('/oauth/register', ['redirect_uris' => ['http://localhost:1/cb']])->assertStatus(503);
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token'])->assertStatus(503);
        $this->get('/oauth/authorize')->assertStatus(503);
    } finally {
        app(MaintenanceMode::class)->deactivate();
    }
});
