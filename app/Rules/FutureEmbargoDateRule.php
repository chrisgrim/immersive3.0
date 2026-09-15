<?php

namespace App\Rules;

use App\Models\Events\Show;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An embargo must still be in the future where the event is.
 *
 * embargo_date is a wall-clock time in the event's own timezone (see
 * Event::embargoLiftsAt), so "after:now" would silently compare it as UTC:
 * the same value the publish cron reads as "still pending" could be refused
 * here, or accepted only to be published on the next cron run. This rule
 * interprets the value exactly as the cron does.
 *
 * The timezone comes from the sibling `timezone` field when the save carries
 * one (the wizard's Dates step always does), otherwise from the event being
 * edited, otherwise UTC, with junk zones read as UTC (Show::validTimezone),
 * exactly as Event::embargoLiftsAt does.
 */
class FutureEmbargoDateRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function __construct(protected ?string $eventTimezone = null) {}

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
        if ($value === null || $value === '') {
            return;
        }

        $timezone = Show::validTimezone($this->data['timezone'] ?? $this->eventTimezone ?? null);

        try {
            $liftsAt = Carbon::createFromFormat('Y-m-d H:i:s', (string) $value, $timezone);
        } catch (\Throwable) {
            // The date_format rule reports the malformed input.
            return;
        }

        if ($liftsAt === false || $liftsAt->lte(Carbon::now())) {
            $fail('The :attribute must be in the future.');
        }
    }
}
