<?php

namespace Crumbls\StateMachine\Exceptions;

use Exception;

class MiddlewareException extends Exception
{
    public static function classNotFound(string $class): static
    {
        return new static("Middleware class {$class} does not exist");
    }

    public static function invalidInterface(string $class): static
    {
        return new static(
            "Middleware {$class} must implement Laravel's middleware interface with handle(StateTransitionRequest \$request, Closure \$next) method"
        );
    }
}