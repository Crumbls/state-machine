<?php

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\WorkflowState;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Draft;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Review;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Approved;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Published;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Rejected;

it('proceeds along the preferred chain', function () {
    $machine = StateMachine::make(WorkflowState::class);

    expect($machine->canProceed())->toBeTrue();
    expect($machine->proceed())->toBeTrue();
    expect($machine->is(Review::class))->toBeTrue();

    expect($machine->proceed())->toBeTrue();
    expect($machine->is(Approved::class))->toBeTrue();

    expect($machine->proceed())->toBeTrue();
    expect($machine->is(Published::class))->toBeTrue();
});

it('returns false from proceed when no preferred transition is set', function () {
    $machine = StateMachine::make(WorkflowState::class);
    $machine->transitionTo(Review::class);
    $machine->transitionTo(Rejected::class);

    expect($machine->canProceed())->toBeFalse();
    expect($machine->proceed())->toBeFalse();
    expect($machine->is(Rejected::class))->toBeTrue();
});

it('rolls back along the rollback chain', function () {
    $machine = StateMachine::make(WorkflowState::class);
    $machine->transitionTo(Review::class);
    $machine->transitionTo(Approved::class);
    $machine->transitionTo(Published::class);

    expect($machine->canRollback())->toBeTrue();
    expect($machine->rollback())->toBeTrue();
    expect($machine->is(Approved::class))->toBeTrue();

    expect($machine->rollback())->toBeTrue();
    expect($machine->is(Review::class))->toBeTrue();

    expect($machine->rollback())->toBeTrue();
    expect($machine->is(Draft::class))->toBeTrue();

    expect($machine->canRollback())->toBeFalse();
    expect($machine->rollback())->toBeFalse();
});

it('returns false from rollback when no rollback transition is set', function () {
    $machine = StateMachine::make(WorkflowState::class);

    expect($machine->canRollback())->toBeFalse();
    expect($machine->rollback())->toBeFalse();
    expect($machine->is(Draft::class))->toBeTrue();
});

it('exposes proceed and rollback through the State delegate', function () {
    $machine = StateMachine::make(WorkflowState::class);
    $state = $machine->getCurrentState();

    expect($state->canProceed())->toBeTrue();
    expect($state->proceed())->toBeTrue();
    expect($machine->is(Review::class))->toBeTrue();

    $state = $machine->getCurrentState();
    expect($state->canRollback())->toBeTrue();
    expect($state->rollback())->toBeTrue();
    expect($machine->is(Draft::class))->toBeTrue();
});

it('autoTransition walks until terminal and reports stopped_reason', function () {
    $machine = StateMachine::make(WorkflowState::class);

    $result = $machine->autoTransition();

    expect($result['transition_count'])->toBe(3);
    expect($result['final_state_class'])->toBe(Published::class);
    expect($result['stopped_reason'])->toBe('no_preferred_transition');
});

it('autoTransition halts when execute() on a state returns false', function () {
    $machine = StateMachine::make(\Crumbls\StateMachine\Tests\ExampleStates\Workflow\ExecutableWorkflowState::class);

    $result = $machine->autoTransition();

    // Halts in Review because Review::execute() returns false.
    expect($result['final_state_class'])->toBe(\Crumbls\StateMachine\Tests\ExampleStates\Workflow\HaltingReview::class);
    expect($result['stopped_reason'])->toBe('execute_failed');
    expect($result['transition_count'])->toBe(1);
});

it('autoTransition respects the max-transitions safety cap', function () {
    $machine = StateMachine::make(\Crumbls\StateMachine\Tests\ExampleStates\Workflow\LoopingState::class);

    $result = $machine->autoTransition(maxTransitions: 5);

    expect($result['transition_count'])->toBe(5);
    expect($result['stopped_reason'])->toBe('max_transitions_reached');
});
