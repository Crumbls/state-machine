<?php

namespace Crumbls\StateMachine\Exceptions;

use Exception;

class StateConfigurationException extends Exception
{
    public static function noDefaultState(string $stateClass): static
    {
        return new static("No default state configured for {$stateClass}");
    }

    public static function invalidStateClass(string $class): static
    {
        return new static("State class {$class} must extend " . \Crumbls\StateMachine\State::class);
    }

    public static function duplicateTransition(string $from, string $to): static
    {
        return new static("Duplicate transition from {$from} to {$to}");
    }
}