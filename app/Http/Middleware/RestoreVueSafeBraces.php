<?php

namespace App\Http\Middleware;

use App\Support\VueSafe;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * VueSafe puts a zero-width space between the braces of any "{{" it prints,
 * including into the JSON props the editors read their data from, so a
 * form sends that character straight back. Taking it out again on the way in
 * makes the round trip exact: nothing gains an invisible character when it
 * is saved, and an unchanged "{{ ... }}" name is not mistaken for a rename.
 */
class RestoreVueSafeBraces extends TransformsRequest
{
    protected function transform($key, $value)
    {
        return is_string($value) ? VueSafe::restore($value) : $value;
    }
}
