<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

/**
 * A ticket link is a web address, or a mailto: link for shows that only sell
 * tickets by email (no website at all). Laravel's `url` rule refuses
 * "mailto:x@y.com" and accepts "https://mailto:x@y.com", which is exactly the
 * broken link organizers ended up saving when they had only an email address.
 */
class TicketUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The ticket link must be a web address or an email address.');

            return;
        }

        if (preg_match('/^mailto:/i', $value)) {
            $address = substr($value, strlen('mailto:'));
            if ($address === '' || Validator::make(['a' => $address], ['a' => 'email'])->fails()) {
                $fail('The ticket email address is not valid.');
            }

            return;
        }

        if (preg_match('/^https?:\/\/mailto:/i', $value)
            || Validator::make(['u' => $value], ['u' => 'url'])->fails()) {
            $fail('The ticket link must be a web address or an email address.');
        }
    }
}
