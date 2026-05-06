<?php

use Crumbls\StateMachine\StateConfig;
use Crumbls\StateMachine\Tests\ExampleStates\OrderState;
use Crumbls\StateMachine\Tests\ExampleStates\Pending;
use Crumbls\StateMachine\Tests\ExampleStates\Processing;
use Crumbls\StateMachine\Tests\ExampleStates\Shipped;
use Crumbls\StateMachine\Tests\ExampleStates\Cancelled;
use Crumbls\StateMachine\Tests\ExampleStates\Delivered;
use Crumbls\StateMachine\Tests\ExampleStates\Workflow\WorkflowState;

it('returns no warnings for the example OrderState graph', function () {
    expect(OrderState::config()->validate())->toBe([]);
});

it('returns no warnings for the example WorkflowState graph', function () {
    expect(WorkflowState::config()->validate())->toBe([]);
});

it('flags preferred transitions that are not allowed', function () {
    $config = (new StateConfig())
        ->default(Pending::class)
        ->allowTransition(Pending::class, Processing::class)
        ->preferredTransition(Pending::class, Shipped::class);

    $warnings = $config->validate();

    expect($warnings)->toContain(
        'Preferred transition ' . Pending::class . ' -> ' . Shipped::class . ' is not in allowedTransitions.'
    );
});

it('flags rollback transitions that are not allowed', function () {
    $config = (new StateConfig())
        ->default(Pending::class)
        ->allowTransition(Pending::class, Processing::class)
        ->rollbackTransition(Processing::class, Cancelled::class);

    $warnings = $config->validate();

    expect($warnings)->toContain(
        'Rollback transition ' . Processing::class . ' -> ' . Cancelled::class . ' is not in allowedTransitions.'
    );
});

it('flags states unreachable from the default state', function () {
    $config = (new StateConfig())
        ->default(Pending::class)
        ->allowTransition(Pending::class, Processing::class)
        ->allowTransition(Shipped::class, Delivered::class);

    $warnings = $config->validate();

    expect($warnings)->toContain(
        'State ' . Shipped::class . ' is unreachable from default state ' . Pending::class . '.'
    );
});

it('flags a default state that is not referenced by any transition', function () {
    $config = (new StateConfig())
        ->default(Pending::class)
        ->allowTransition(Processing::class, Shipped::class);

    $warnings = $config->validate();

    expect($warnings[0])->toBe(
        'Default state ' . Pending::class . ' is not referenced by any transition.'
    );
});

it('flags guards registered for unreachable transitions', function () {
    $config = (new StateConfig())
        ->default(Pending::class)
        ->allowTransition(Pending::class, Processing::class)
        ->guard(Pending::class, Shipped::class, fn () => true);

    $warnings = $config->validate();

    expect($warnings)->toContain(
        'Guard registered for unreachable transition ' . Pending::class . ' -> ' . Shipped::class . '.'
    );
});

it('flags onTransition callbacks registered for unreachable transitions', function () {
    $config = (new StateConfig())
        ->default(Pending::class)
        ->allowTransition(Pending::class, Processing::class)
        ->onTransition(Pending::class, Shipped::class, fn () => null);

    $warnings = $config->validate();

    expect($warnings)->toContain(
        'Callback registered for unreachable transition ' . Pending::class . ' -> ' . Shipped::class . '.'
    );
});
