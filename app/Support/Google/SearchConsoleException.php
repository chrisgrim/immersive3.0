<?php

namespace App\Support\Google;

use RuntimeException;
use Throwable;

class SearchConsoleException extends RuntimeException
{
    public function __construct(string $message, private bool $fatal = false, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Every later call would fail the same way (no access, wrong property). */
    public function fatal(): bool
    {
        return $this->fatal;
    }
}
