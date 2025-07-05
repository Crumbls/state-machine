<?php

namespace Crumbls\StateMachine\Tests\Support;

use Crumbls\StateMachine\Http\StateTransitionRequest;
use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\State;
use Closure;

class TestMiddleware
{
    public static array $executionLog = [];
    public static bool $shouldFail = false;

    public function __construct(
        public string $name = 'test'
    ) {}

    public function handle(StateTransitionRequest $request, Closure $next)
    {
        static::$executionLog[] = "{$this->name}_before";
        
        if (static::$shouldFail) {
            throw new \Exception('Test middleware failed');
        }
        
        $result = $next($request);
        
        static::$executionLog[] = "{$this->name}_after";
        
        return $result;
    }

    public static function reset(): void
    {
        static::$executionLog = [];
        static::$shouldFail = false;
    }
}