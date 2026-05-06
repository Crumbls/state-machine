<?php

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;
use Crumbls\StateMachine\Exceptions\InvalidTransitionException;

beforeEach(function () {
    GuardedState::reset();
});

it('runs guard with the current state and context', function () {
    GuardedState::$guard = function (State $state, array $context) {
        GuardedState::$received = ['state' => $state::class, 'context' => $context];
        return true;
    };

    $machine = StateMachine::make(GuardedState::class, ['actor_id' => 99]);
    $machine->transitionTo(GuardedB::class, ['note' => 'go']);

    expect(GuardedState::$received['state'])->toBe(GuardedA::class);
    expect(GuardedState::$received['context'])->toBe(['actor_id' => 99]);
    expect($machine->is(GuardedB::class))->toBeTrue();
});

it('blocks the transition when the guard returns false', function () {
    GuardedState::$guard = fn () => false;

    $machine = StateMachine::make(GuardedState::class);

    expect($machine->canTransitionTo(GuardedB::class))->toBeFalse();
    expect(fn () => $machine->transitionTo(GuardedB::class))
        ->toThrow(InvalidTransitionException::class);
});

it('skips the guard when no guard is registered', function () {
    GuardedState::$guard = null;

    $machine = StateMachine::make(GuardedState::class);

    // No guard wraps the C transition; the underlying $guard is the registered
    // closure, which returns the static value. With null it returns null
    // (falsy), blocking the transition. Use the no-guard transition:
    expect($machine->canTransitionTo(GuardedC::class))->toBeTrue();
});

abstract class GuardedState extends State
{
    public static mixed $guard = null;
    public static ?array $received = null;

    public static function reset(): void
    {
        self::$guard = null;
        self::$received = null;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(GuardedA::class)
            ->allowTransition(GuardedA::class, GuardedB::class)
            ->allowTransition(GuardedA::class, GuardedC::class)
            ->guard(GuardedA::class, GuardedB::class, function (State $state, array $context) {
                $guard = GuardedState::$guard;
                if (is_callable($guard)) {
                    return $guard($state, $context);
                }
                return $guard;
            });
    }
}

class GuardedA extends GuardedState
{
}

class GuardedB extends GuardedState
{
}

class GuardedC extends GuardedState
{
}
