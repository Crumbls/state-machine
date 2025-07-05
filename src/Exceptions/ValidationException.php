<?php

namespace Crumbls\StateMachine\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public static function forTransition(string $from, string $to, array $errors): static
    {
        return new static(
            "Validation failed for transition from {$from} to {$to}: " .
            implode(', ', $errors)
        );
    }
}