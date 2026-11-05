<?php

namespace App\Mcp\Tools\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;

/**
 * The shared project actions refuse with exceptions, which the website turns
 * into a 422 or a 403. Here they are an error the assistant can read out.
 */
trait AnswersShowActions
{
    protected function attempt(callable $action): Response
    {
        try {
            return Response::json($action());
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? $e->getMessage());
        } catch (AuthorizationException) {
            return Response::error('That needs moderator powers or a team that keeps this show.');
        }
    }

    protected const MODERATORS_ONLY = 'Moderators only, and it needs a connection with moderator powers (the API key option, or "Include moderator powers" on the consent screen).';
}
