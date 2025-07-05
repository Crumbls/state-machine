<?php

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\Tests\ExampleStates\TestOrderStateWithMiddleware;
use Crumbls\StateMachine\Tests\ExampleStates\Processing;
use Crumbls\StateMachine\Tests\Support\TestMiddleware;
use Crumbls\StateMachine\Middleware\RateLimitMiddleware;
use Crumbls\StateMachine\Middleware\ValidationMiddleware;
use Crumbls\StateMachine\Exceptions\RateLimitExceededException;
use Crumbls\StateMachine\Exceptions\ValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Cache::flush();
    TestMiddleware::reset();
    TestOrderStateWithMiddleware::clearMiddleware();
    Log::spy();
});

it('can apply middleware to state transitions', function () {
    $middleware = new TestMiddleware('test');
    TestOrderStateWithMiddleware::setMiddleware([$middleware]);
    
    $machine = StateMachine::make(TestOrderStateWithMiddleware::class);
    $machine->transitionTo(Processing::class);

    expect(TestMiddleware::$executionLog)->toBe(['test_before', 'test_after']);
});

it('enforces rate limiting', function () {
    $rateLimiter = new RateLimitMiddleware(1, 1); // 1 attempt per minute
    TestOrderStateWithMiddleware::setMiddleware([$rateLimiter]);
    
    $context = ['model_id' => 1, 'model_type' => 'Order'];
    
    // First should work
    $machine1 = StateMachine::make(TestOrderStateWithMiddleware::class, $context);
    $machine1->transitionTo(Processing::class);
    
    // Reset to same state for second attempt
    $machine2 = StateMachine::make(TestOrderStateWithMiddleware::class, $context);
    expect(fn() => $machine2->transitionTo(Processing::class))
        ->toThrow(RateLimitExceededException::class);
});

it('enforces validation', function () {
    $validator = ValidationMiddleware::rules(['user_id' => 'required|integer']);
    TestOrderStateWithMiddleware::setMiddleware([$validator]);
    
    $machine = StateMachine::make(TestOrderStateWithMiddleware::class);
    
    // Should fail
    expect(fn() => $machine->transitionTo(Processing::class, ['user_id' => 'invalid']))
        ->toThrow(ValidationException::class);
    
    // Should pass
    $machine->transitionTo(Processing::class, ['user_id' => 123]);
    expect($machine->is(Processing::class))->toBeTrue();
});

it('handles middleware exceptions', function () {
    TestMiddleware::$shouldFail = true;
    $middleware = new TestMiddleware('failing');
    TestOrderStateWithMiddleware::setMiddleware([$middleware]);
    
    $machine = StateMachine::make(TestOrderStateWithMiddleware::class);
    
    expect(fn() => $machine->transitionTo(Processing::class))
        ->toThrow(\Exception::class);
});