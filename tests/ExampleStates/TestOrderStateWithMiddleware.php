<?php

namespace Crumbls\StateMachine\Tests\ExampleStates;

use Crumbls\StateMachine\StateConfig;

class TestOrderStateWithMiddleware extends OrderState
{
    public static array $appliedMiddleware = [];

    public function color(): string
    {
        return 'test';
    }

    public static function config(): StateConfig
    {
        return parent::config()->middleware(static::$appliedMiddleware);
    }

    public static function setMiddleware(array $middleware): void
    {
        static::$appliedMiddleware = $middleware;
    }

    public static function clearMiddleware(): void
    {
        static::$appliedMiddleware = [];
    }
}