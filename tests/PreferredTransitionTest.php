<?php

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\Exceptions\InvalidTransitionException;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\WorkflowState;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Draft;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Review;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Approved;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Published;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Rejected;

it('starts the workflow in the default state', function () {
    $machine = StateMachine::make(WorkflowState::class);

    expect($machine->is(Draft::class))->toBeTrue();
});

it('walks the workflow forward using preferred transitions', function () {
    $machine = StateMachine::make(WorkflowState::class);
    $config = WorkflowState::config();

    $expectedPath = [Review::class, Approved::class, Published::class];

    foreach ($expectedPath as $expected) {
        $next = $config->getPreferredTransition($machine->getCurrentStateName());

        expect($next)->toBe($expected);

        $machine->transitionTo($next);

        expect($machine->is($expected))->toBeTrue();
    }

    expect($config->getPreferredTransition($machine->getCurrentStateName()))->toBeNull();
});

it('walks the workflow backwards using rollback transitions', function () {
    $machine = StateMachine::make(WorkflowState::class);
    $config = WorkflowState::config();

    $machine->transitionTo(Review::class);
    $machine->transitionTo(Approved::class);
    $machine->transitionTo(Published::class);

    $expectedPath = [Approved::class, Review::class, Draft::class];

    foreach ($expectedPath as $expected) {
        $previous = $config->getRollbackTransition($machine->getCurrentStateName());

        expect($previous)->toBe($expected);

        $machine->transitionTo($previous);

        expect($machine->is($expected))->toBeTrue();
    }

    expect($config->getRollbackTransition($machine->getCurrentStateName()))->toBeNull();
});

it('exposes preferred transitions for every non-terminal workflow state', function () {
    $config = WorkflowState::config();

    expect($config->getPreferredTransitions())->toBe([
        Draft::class => Review::class,
        Review::class => Approved::class,
        Approved::class => Published::class,
    ]);
});

it('exposes rollback transitions for every reversible workflow state', function () {
    $config = WorkflowState::config();

    expect($config->getRollbackTransitions())->toBe([
        Review::class => Draft::class,
        Approved::class => Review::class,
        Published::class => Approved::class,
    ]);
});

it('still allows non-preferred transitions when configured', function () {
    $machine = StateMachine::make(WorkflowState::class);
    $machine->transitionTo(Review::class);

    expect($machine->canTransitionTo(Rejected::class))->toBeTrue();

    $machine->transitionTo(Rejected::class);

    expect($machine->is(Rejected::class))->toBeTrue();
});

it('blocks rollback when no rollback is registered', function () {
    $config = WorkflowState::config();

    expect($config->getRollbackTransition(Draft::class))->toBeNull();
});

it('does not auto-allow a preferred transition that has not been allowed', function () {
    $config = (new \Crumbls\StateMachine\StateConfig())
        ->default(Draft::class)
        ->preferredTransition(Draft::class, Published::class);

    expect($config->isTransitionAllowed(Draft::class, Published::class))->toBeFalse();
});

it('refuses to transition to a preferred state that is not allowed', function () {
    // A state graph where Pending's preferred next state is not actually allowed.
    $machine = StateMachine::make(\Crumbls\StateMachine\Tests\ExampleStates\Workflow\PreferredOnlyState::class);

    expect(fn () => $machine->transitionTo(Published::class))
        ->toThrow(InvalidTransitionException::class);
});
