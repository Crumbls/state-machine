<?php

use Crumbls\StateMachine\StateMachineManager;
use Crumbls\StateMachine\Tests\ExampleStates\OrderState;
use Crumbls\StateMachine\Tests\ExampleStates\Pending;

it('can create and manage state machines', function () {
    $manager = new StateMachineManager();
    
    $machine = $manager->create('order-123', OrderState::class);
    
    expect($manager->has('order-123'))->toBeTrue();
    expect($manager->get('order-123'))->toBe($machine);
    expect($manager->get('non-existent'))->toBeNull();
});

it('can remove state machines', function () {
    $manager = new StateMachineManager();
    
    $manager->create('order-123', OrderState::class);
    expect($manager->has('order-123'))->toBeTrue();
    
    $manager->remove('order-123');
    expect($manager->has('order-123'))->toBeFalse();
});

it('can list all state machines', function () {
    $manager = new StateMachineManager();
    
    $machine1 = $manager->create('order-123', OrderState::class);
    $machine2 = $manager->create('order-456', OrderState::class);
    
    $all = $manager->all();
    expect($all)->toHaveCount(2);
    expect($all['order-123'])->toBe($machine1);
    expect($all['order-456'])->toBe($machine2);
});

it('can make standalone state machines', function () {
    $manager = new StateMachineManager();
    
    $machine = $manager->make(OrderState::class);
    
    expect($machine)->toBeInstanceOf(\Crumbls\StateMachine\StateMachine::class);
    expect($machine->getCurrentState())->toBeInstanceOf(Pending::class);
});