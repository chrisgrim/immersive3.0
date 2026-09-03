<?php

namespace App\Rules;

use App\Support\Currency;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A price in a currency with no minor unit must be a whole number.
 *
 * JPY, KRW and the other zero-decimal currencies (per CLDR, via
 * Currency::decimals) are written without decimals everywhere they are
 * displayed, so a stored 144000.50 in KRW has no correct rendering: the
 * wizard's editor shows "144000.5" and the live listing rounds to
 * "₩144,001", leaving the page quoting a price the database does not hold.
 * Rejecting the value on the way in is the only place that divergence can
 * actually be closed — making every display surface agree on a rounding
 * rule would still leave the stored number unrepresentable.
 *
 * Reads the sibling `currency` from the validator's own data (DataAwareRule)
 * rather than the request: these rules are shared by the web wizard, the MCP
 * tools and direct validator() calls in tests, and only the first of those
 * has a request carrying the ticket array.
 */
class ZeroDecimalPriceRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return; // `numeric` reports this; don't pile on a second message.
        }

        // "tickets.0.ticket_price" → the name and currency of that same tier.
        $index = explode('.', $attribute)[1] ?? null;

        // A PWYC tier's price is a sentinel, never rendered: the wizard writes
        // 0.01 to mean "not free, pay what you can", hides the price input,
        // and every price surface prints "PWYC" in its place (Ticket::
        // priceRange, show-purchase.vue — same case-insensitive test). No
        // decimal amount is being quoted, so there is nothing to reject; a
        // rejection here was one the organizer had no field to act on.
        $name = data_get($this->data, "tickets.{$index}.name");
        if (is_string($name) && strtoupper(trim($name)) === 'PWYC') {
            return;
        }

        $currency = data_get($this->data, "tickets.{$index}.currency");
        $currency = Currency::normalize($currency);

        if (! Currency::isValid($currency) || Currency::decimals($currency) !== 0) {
            return;
        }

        if (floor((float) $value) != (float) $value) {
            $fail("A price in {$currency} is written without decimals — use a whole number.");
        }
    }
}
