<?php

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\Tests\ExampleStates\OrderState;
use Crumbls\StateMachine\Tests\ExampleStates\Pending;
use Crumbls\StateMachine\Tests\ExampleStates\Processing;
use Crumbls\StateMachine\Tests\ExampleStates\Shipped;

it('returns true and transitions on a valid attempt', function () {
    $machine = StateMachine::make(OrderState::class);

    expect($machine->tryTransitionTo(Processing::class))->toBeTrue();
    expect($machine->is(Processing::class))->toBeTrue();
});

it('returns false without throwing on a disallowed attempt', function () {
    $machine = StateMachine::make(OrderState::class);

    expect($machine->tryTransitionTo(Shipped::class))->toBeFalse();
    expect($machine->is(Pending::class))->toBeTrue();
});

it('exposes tryTransitionTo through State', function () {
    $machine = StateMachine::make(OrderState::class);

    expect($machine->getCurrentState()->tryTransitionTo(Shipped::class))->toBeFalse();
    expect($machine->is(Pending::class))->toBeTrue();

    expect($machine->getCurrentState()->tryTransitionTo(Processing::class))->toBeTrue();
    expect($machine->is(Processing::class))->toBeTrue();
});
