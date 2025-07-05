<?php

namespace Crumbls\StateMachine\Exceptions;

use Exception;

class TimingException extends Exception
{
    public static function executionTimeExceeded(string $from, string $to, int $actualMs, int $maxMs): static
    {
        return new static(
            "State transition from {$from} to {$to} took {$actualMs}ms, " .
            "exceeding maximum allowed time of {$maxMs}ms"
        );
    }
}