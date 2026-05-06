<?php

use Crumbls\StateMachine\Jobs\ProcessStateTransition;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\WorkflowState;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Draft;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Review;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\Approved;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Bus::fake();
    Event::fake();
});

it('continues to the preferred transition when one is set even if multiple are allowed', function () {
    $machineData = [
        'state_class' => WorkflowState::class,
        'current_state' => Draft::class,
        'context' => [],
    ];

    // Land in Review (which has multiple allowed exits: Approved, Rejected, Draft)
    // and ensure auto-continuation picks Approved (the preferred next).
    $job = new ProcessStateTransition(
        $machineData,
        Review::class,
        [],
        'workflow-1',
        true,
    );

    $job->handle();

    Bus::assertDispatched(ProcessStateTransition::class, function (ProcessStateTransition $next) {
        return $next->toState === Approved::class
            && $next->identifier === 'workflow-1'
            && $next->continueOnSuccess === true;
    });
});

it('does not auto-continue when more than one transition is allowed and none is preferred', function () {
    $machineData = [
        'state_class' => \Crumbls\StateMachine\Tests\ExampleStates\OrderState::class,
        'current_state' => \Crumbls\StateMachine\Tests\ExampleStates\Pending::class,
        'context' => [],
    ];

    // OrderState's Processing has two allowed exits (Shipped, Cancelled) and no
    // preferred transition; the job should not auto-pick a branch.
    $job = new ProcessStateTransition(
        $machineData,
        \Crumbls\StateMachine\Tests\ExampleStates\Processing::class,
        [],
        'order-1',
        true,
    );

    $job->handle();

    Bus::assertNotDispatched(ProcessStateTransition::class);
});
