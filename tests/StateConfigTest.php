<?php

use Crumbls\StateMachine\StateConfig;
use Crumbls\StateMachine\Exceptions\StateConfigurationException;
use Crumbls\StateMachine\Tests\ExampleStates\OrderState;
use Crumbls\StateMachine\Tests\ExampleStates\Pending;
use Crumbls\StateMachine\Tests\ExampleStates\Processing;
use Crumbls\StateMachine\Tests\ExampleStates\Shipped;
use Crumbls\StateMachine\Tests\ExampleStates\Cancelled;
use Crumbls\StateMachine\Tests\ExampleStates\Delivered;

it('returns null default state when none configured', function () {
    $config = new StateConfig();

    expect($config->getDefaultState())->toBeNull();
});

it('records the default state', function () {
    $config = (new StateConfig())->default(Pending::class);

    expect($config->getDefaultState())->toBe(Pending::class);
});

it('records allowed transitions', function () {
    $config = (new StateConfig())
        ->allowTransition(Pending::class, Processing::class)
        ->allowTransition(Pending::class, Cancelled::class);

    expect($config->isTransitionAllowed(Pending::class, Processing::class))->toBeTrue();
    expect($config->isTransitionAllowed(Pending::class, Cancelled::class))->toBeTrue();
    expect($config->isTransitionAllowed(Pending::class, Shipped::class))->toBeFalse();
    expect($config->isTransitionAllowed(Processing::class, Pending::class))->toBeFalse();
});

it('throws on duplicate allowed transitions', function () {
    $config = (new StateConfig())
        ->allowTransition(Pending::class, Processing::class);

    expect(fn () => $config->allowTransition(Pending::class, Processing::class))
        ->toThrow(StateConfigurationException::class);
});

it('records multiple allowed transitions at once', function () {
    $config = (new StateConfig())
        ->allowTransitions(Pending::class, [Processing::class, Cancelled::class]);

    expect($config->isTransitionAllowed(Pending::class, Processing::class))->toBeTrue();
    expect($config->isTransitionAllowed(Pending::class, Cancelled::class))->toBeTrue();
});

it('returns null when no preferred transition is registered', function () {
    $config = new StateConfig();

    expect($config->getPreferredTransition(Pending::class))->toBeNull();
    expect($config->getPreferredTransitions())->toBe([]);
});

it('records a preferred transition', function () {
    $config = (new StateConfig())
        ->preferredTransition(Pending::class, Processing::class);

    expect($config->getPreferredTransition(Pending::class))->toBe(Processing::class);
    expect($config->getPreferredTransitions())->toBe([
        Pending::class => Processing::class,
    ]);
});

it('overrides a preferred transition when re-registered for the same source', function () {
    $config = (new StateConfig())
        ->preferredTransition(Pending::class, Processing::class)
        ->preferredTransition(Pending::class, Cancelled::class);

    expect($config->getPreferredTransition(Pending::class))->toBe(Cancelled::class);
});

it('keeps preferred transitions independent of allowed transitions', function () {
    $config = (new StateConfig())
        ->preferredTransition(Pending::class, Processing::class);

    expect($config->getPreferredTransition(Pending::class))->toBe(Processing::class);
    expect($config->isTransitionAllowed(Pending::class, Processing::class))->toBeFalse();
});

it('returns null when no rollback transition is registered', function () {
    $config = new StateConfig();

    expect($config->getRollbackTransition(Processing::class))->toBeNull();
    expect($config->getRollbackTransitions())->toBe([]);
});

it('records a rollback transition', function () {
    $config = (new StateConfig())
        ->rollbackTransition(Processing::class, Pending::class);

    expect($config->getRollbackTransition(Processing::class))->toBe(Pending::class);
    expect($config->getRollbackTransitions())->toBe([
        Processing::class => Pending::class,
    ]);
});

it('overrides a rollback transition when re-registered for the same source', function () {
    $config = (new StateConfig())
        ->rollbackTransition(Processing::class, Pending::class)
        ->rollbackTransition(Processing::class, Cancelled::class);

    expect($config->getRollbackTransition(Processing::class))->toBe(Cancelled::class);
});

it('keeps preferred and rollback collections separate', function () {
    $config = (new StateConfig())
        ->preferredTransition(Processing::class, Shipped::class)
        ->rollbackTransition(Processing::class, Pending::class);

    expect($config->getPreferredTransition(Processing::class))->toBe(Shipped::class);
    expect($config->getRollbackTransition(Processing::class))->toBe(Pending::class);
});

it('exposes guards by from/to pair', function () {
    $guard = fn () => true;
    $config = (new StateConfig())->guard(Pending::class, Processing::class, $guard);

    expect($config->hasGuard(Pending::class, Processing::class))->toBeTrue();
    expect($config->hasGuard(Pending::class, Shipped::class))->toBeFalse();
    expect($config->getGuard(Pending::class, Processing::class))->toBe($guard);
    expect($config->getGuard(Pending::class, Shipped::class))->toBeNull();
});

it('records callbacks for transitions, enter, and exit', function () {
    $transitionCallback = fn () => null;
    $enterCallback = fn () => null;
    $exitCallback = fn () => null;

    $config = (new StateConfig())
        ->onTransition(Pending::class, Processing::class, $transitionCallback)
        ->onEnter(Processing::class, $enterCallback)
        ->onExit(Pending::class, $exitCallback);

    expect($config->getCallbacksFor(Pending::class, Processing::class))->toBe([$transitionCallback]);
    expect($config->getCallbacksFor('enter', Processing::class))->toBe([$enterCallback]);
    expect($config->getCallbacksFor('exit', Pending::class))->toBe([$exitCallback]);
});

it('returns an empty array for callback lookups with no matches', function () {
    $config = new StateConfig();

    expect($config->getCallbacksFor('enter', Pending::class))->toBe([]);
    expect($config->getCallbacksFor(Pending::class, Processing::class))->toBe([]);
});

it('appends middleware via string, array, or object', function () {
    $object = new \stdClass();
    $config = (new StateConfig())
        ->middleware('first')
        ->middleware(['second', 'third'])
        ->middleware($object);

    expect($config->getMiddleware())->toBe(['first', 'second', 'third', $object]);
});

it('exposes the full OrderState transition graph', function () {
    $config = OrderState::config();

    expect($config->getDefaultState())->toBe(Pending::class);
    expect($config->getAllowedTransitions())->toBe([
        Pending::class => [Processing::class, Cancelled::class],
        Processing::class => [Shipped::class, Cancelled::class],
        Shipped::class => [Delivered::class],
    ]);
});
