<?php

namespace Crumbls\StateMachine\Exceptions;

use Exception;

class GuardException extends Exception
{
    public static function guardFailed(string $from, string $to, string $reason = ''): self
    {
        $message = "Guard failed for transition from {$from} to {$to}";
        if ($reason !== '') {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    public static function guardNotCallable(string $from, string $to): self
    {
        return new self("Guard for transition from {$from} to {$to} is not callable");
    }
}