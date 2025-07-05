<?php

namespace Crumbls\StateMachine\Exceptions;

use Exception;

class StateSerializationException extends Exception
{
    public static function invalidData(string $reason): static
    {
        return new static("Invalid serialization data: {$reason}");
    }

    public static function missingKey(string $key): static
    {
        return new static("Missing required key '{$key}' in serialization data");
    }

    public static function invalidJson(string $json): static
    {
        return new static("Invalid JSON data: {$json}");
    }
}