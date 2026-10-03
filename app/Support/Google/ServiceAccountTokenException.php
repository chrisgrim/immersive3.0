<?php

namespace App\Support\Google;

use RuntimeException;

class ServiceAccountTokenException extends RuntimeException
{
    public function __construct(string $message, private bool $retryable = false)
    {
        parent::__construct($message);
    }

    /** Google was busy (429 or 5xx), not refusing the key. */
    public function retryable(): bool
    {
        return $this->retryable;
    }
}
