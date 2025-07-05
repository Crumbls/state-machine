<?php

namespace Crumbls\StateMachine\Exceptions;

use Exception;

class ThrottleException extends Exception
{
    public static function tooManyAttempts(string $from, string $to, string $retryAfter): static
    {
        return new static(
            "Too many transition attempts from {$from} to {$to}. Please try again after {$retryAfter}."
        );
    }

    public static function currentlyLocked(string $from, string $to, string $expiresAt): static
    {
        return new static(
            "Transition from {$from} to {$to} is currently locked until {$expiresAt}."
        );
    }
}