<?php

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

trait BuildsSyntheticRequests
{
    /**
     * Build an Illuminate Request that UpdateEventAction (and the static model
     * helpers it calls: Show::saveShows, Ticket::handleTickets, ...) can read
     * exactly like the web wizard's request. File-upload branches simply never
     * trigger because a synthesized request carries no files.
     */
    protected function syntheticRequest(array $data, Authenticatable $user): Request
    {
        $request = new Request;
        $request->replace($data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
