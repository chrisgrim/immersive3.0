<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetEvent;
use App\Mcp\Tools\ListAllEvents;
use App\Mcp\Tools\Whoami;
use Laravel\Mcp\Server;

/**
 * The read-only door (/mcp/read, routes/ai.php). It holds only tools that read,
 * so a key that opens it can change nothing whatever it is asked to do: the
 * boundary is the tool list, not a prompt. Its keys carry mcp:read alone
 * (User::READ_SCOPE), and see what the website shows anyone: published events.
 */
class EiReadServer extends Server
{
    protected string $name = 'Everything Immersive (read only)';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
    Read-only access to Everything Immersive, an event discovery platform for
    immersive experiences. Nothing here changes anything.

    `list-all-events` lists published events (use `closing_before` to find runs
    about to expire). `get-event` returns one published event's full state by
    slug, including its schedule; pass summary=true to collapse the schedule to
    a count plus first/last date. `whoami` names the account.
    MARKDOWN;

    protected array $tools = [
        Whoami::class,
        ListAllEvents::class,
        GetEvent::class,
    ];
}
