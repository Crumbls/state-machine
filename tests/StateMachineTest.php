<?php

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\Tests\ExampleStates\OrderState;
use Crumbls\StateMachine\Tests\ExampleStates\Pending;
use Crumbls\StateMachine\Tests\ExampleStates\Processing;
use Crumbls\StateMachine\Tests\ExampleStates\Shipped;
use Crumbls\StateMachine\Tests\ExampleStates\Delivered;
use Crumbls\StateMachine\Tests\ExampleStates\Cancelled;
use Crumbls\StateMachine\Exceptions\InvalidTransitionException;
use Crumbls\StateMachine\Exceptions\StateConfigurationException;
use Crumbls\StateMachine\Exceptions\StateSerializationException;

it('can create a state machine', function () {
    $machine = StateMachine::make(OrderState::class);
    
    expect($machine)->toBeInstanceOf(StateMachine::class);
    expect($machine->getCurrentState())->toBeInstanceOf(Pending::class);
});

it('can check current state', function () {
    $machine = StateMachine::make(OrderState::class);
    
    expect($machine->is(Pending::class))->toBeTrue();
    expect($machine->is(Processing::class))->toBeFalse();
});

it('can transition to allowed state', function () {
    $machine = StateMachine::make(OrderState::class);
    
    expect($machine->canTransitionTo(Processing::class))->toBeTrue();
    
    $machine->transitionTo(Processing::class);
    
    expect($machine->is(Processing::class))->toBeTrue();
    expect($machine->is(Pending::class))->toBeFalse();
});

it('cannot transition to disallowed state', function () {
    $machine = StateMachine::make(OrderState::class);
    
    expect($machine->canTransitionTo(Shipped::class))->toBeFalse();
    
    expect(fn() => $machine->transitionTo(Shipped::class))
        ->toThrow(InvalidTransitionException::class);
});

it('can access state methods', function () {
    $machine = StateMachine::make(OrderState::class);
    
    expect($machine->getCurrentState()->color())->toBe('yellow');
    
    $machine->transitionTo(Processing::class);
    expect($machine->getCurrentState()->color())->toBe('blue');
});

it('can handle context', function () {
    $context = ['order_id' => 123, 'user_id' => 456];
    $machine = StateMachine::make(OrderState::class, $context);
    
    expect($machine->getContext())->toBe($context);
    expect($machine->getCurrentState()->getContext())->toBe($context);
});

it('can merge context on transition', function () {
    $machine = StateMachine::make(OrderState::class, ['order_id' => 123]);
    
    $machine->transitionTo(Processing::class, ['processed_at' => '2023-01-01']);
    
    $context = $machine->getContext();
    expect($context['order_id'])->toBe(123);
    expect($context)->toHaveKey('processed_at');
});

it('can transition through multiple states', function () {
    $machine = StateMachine::make(OrderState::class);
    
    $machine->transitionTo(Processing::class);
    expect($machine->is(Processing::class))->toBeTrue();
    
    $machine->transitionTo(Shipped::class);
    expect($machine->is(Shipped::class))->toBeTrue();
    
    expect($machine->canTransitionTo(Delivered::class))->toBeTrue();
    expect($machine->canTransitionTo(Cancelled::class))->toBeFalse();
});

it('can serialize and deserialize state machine', function () {
    $machine = StateMachine::make(OrderState::class, ['order_id' => 123]);
    $machine->transitionTo(Processing::class);
    
    $serialized = $machine->serialize();
    $restored = StateMachine::unserialize($serialized);
    
    expect($restored->is(Processing::class))->toBeTrue();
    expect($restored->getContext()['order_id'])->toBe(123);
});

it('can convert to and from JSON', function () {
    $machine = StateMachine::make(OrderState::class, ['order_id' => 123]);
    $machine->transitionTo(Processing::class);
    
    $json = $machine->toJson();
    $restored = StateMachine::fromJson($json);
    
    expect($restored->is(Processing::class))->toBeTrue();
    expect($restored->getContext()['order_id'])->toBe(123);
});

it('can convert to array', function () {
    $machine = StateMachine::make(OrderState::class, ['order_id' => 123]);
    $machine->transitionTo(Processing::class);
    
    $array = $machine->toArray();
    
    expect($array)->toHaveKeys(['state_class', 'current_state', 'context']);
    expect($array['state_class'])->toBe(OrderState::class);
    expect($array['current_state'])->toBe(Processing::class);
    expect($array['context']['order_id'])->toBe(123);
});

it('throws exception for invalid state class', function () {
    expect(fn() => StateMachine::make('InvalidClass'))
        ->toThrow(StateConfigurationException::class);
});

it('throws exception for invalid JSON', function () {
    expect(fn() => StateMachine::fromJson('invalid json'))
        ->toThrow(StateSerializationException::class);
});

it('throws exception for missing serialization keys', function () {
    expect(fn() => StateMachine::restore(['incomplete' => 'data']))
        ->toThrow(StateSerializationException::class);
});