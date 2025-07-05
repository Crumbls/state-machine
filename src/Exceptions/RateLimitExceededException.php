<?php

namespace Crumbls\StateMachine\Exceptions;

use Exception;

class RateLimitExceededException extends Exception
{
    public static function forTransition(string $from, string $to, int $maxAttempts, int $minutes): static
    {
        return new static(
            "Rate limit exceeded for transition from {$from} to {$to}. " .
            "Max {$maxAttempts} attempts per {$minutes} minutes."
        );
    }
}