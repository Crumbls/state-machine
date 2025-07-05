<?php

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\Tests\ExampleStates\OrderState;
use Crumbls\StateMachine\Tests\ExampleStates\Processing;
use Crumbls\StateMachine\Jobs\ProcessStateTransition;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Queue::fake();
    Event::fake();
    Log::spy();
});

it('can dispatch async transitions', function () {
    $machine = StateMachine::make(OrderState::class);
    
    $machine->transitionToAsync(Processing::class, [], 'test-123');
    
    Queue::assertPushed(ProcessStateTransition::class);
});

it('can dispatch async transitions with continuation', function () {
    $machine = StateMachine::make(OrderState::class);
    
    $machine->transitionToAsyncWithContinuation(Processing::class, [], 'test-123');
    
    Queue::assertPushed(ProcessStateTransition::class);
});

it('processes async transitions', function () {
    $machineData = [
        'state_class' => OrderState::class,
        'current_state' => \Crumbls\StateMachine\Tests\ExampleStates\Pending::class,
        'context' => []
    ];
    
    $job = new ProcessStateTransition(
        $machineData,
        Processing::class,
        [],
        'test-123',
        false
    );
    
    $job->handle();
    
    // Just verify it doesn't throw
    expect(true)->toBeTrue();
});