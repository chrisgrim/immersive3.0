<?php

namespace App\Exceptions;

use App\Models\SavedSearch;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown by UpdateSavedSearchAction when an edit's new criteria fingerprint
 * matches another of the user's own saved searches — the (user_id,
 * fingerprint) unique constraint would otherwise surface as a raw 500
 * instead of a clean, actionable response.
 */
class DuplicateSavedSearchException extends Exception
{
    public function __construct(public readonly SavedSearch $existing)
    {
        parent::__construct("Duplicate of saved search #{$existing->id}");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => "You already have a saved search just like this — \"{$this->existing->name}\".",
            'existing_search_id' => $this->existing->id,
        ], 409);
    }
}
