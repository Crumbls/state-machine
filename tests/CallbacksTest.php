<?php

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;

beforeEach(function () {
    CallbackTracker::$log = [];
});

it('fires onExit, onTransition, then onEnter in order', function () {
    $machine = StateMachine::make(CallbackState::class);

    $machine->transitionTo(CallbackB::class);

    expect(CallbackTracker::$log)->toBe([
        'exit:' . CallbackA::class,
        'transition:' . CallbackA::class . '->' . CallbackB::class,
        'enter:' . CallbackB::class,
    ]);
});

it('passes the new state and merged context into callbacks', function () {
    $machine = StateMachine::make(CallbackState::class, ['actor' => 'system']);

    $machine->transitionTo(CallbackB::class, ['note' => 'go']);

    expect(CallbackTracker::$payload['enter']['state'])->toBe(CallbackB::class);
    expect(CallbackTracker::$payload['enter']['context'])->toBe([
        'actor' => 'system',
        'note' => 'go',
    ]);
});

it('logs and rethrows when a callback throws', function () {
    $machine = StateMachine::make(ThrowingCallbackState::class);

    expect(fn () => $machine->transitionTo(ThrowingB::class))
        ->toThrow(\RuntimeException::class, 'oops');
});

class CallbackTracker
{
    /** @var array<int, string> */
    public static array $log = [];
    /** @var array<string, array{state: string, context: array<string, mixed>}> */
    public static array $payload = [];
}

abstract class CallbackState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(CallbackA::class)
            ->allowTransition(CallbackA::class, CallbackB::class)
            ->onExit(CallbackA::class, function (State $state) {
                CallbackTracker::$log[] = 'exit:' . $state::class;
                CallbackTracker::$payload['exit'] = [
                    'state' => $state::class,
                    'context' => $state->getContext(),
                ];
            })
            ->onTransition(CallbackA::class, CallbackB::class, function (State $state) {
                CallbackTracker::$log[] = 'transition:' . CallbackA::class . '->' . CallbackB::class;
                CallbackTracker::$payload['transition'] = [
                    'state' => $state::class,
                    'context' => $state->getContext(),
                ];
            })
            ->onEnter(CallbackB::class, function (State $state) {
                CallbackTracker::$log[] = 'enter:' . $state::class;
                CallbackTracker::$payload['enter'] = [
                    'state' => $state::class,
                    'context' => $state->getContext(),
                ];
            });
    }
}

class CallbackA extends CallbackState
{
}

class CallbackB extends CallbackState
{
}

abstract class ThrowingCallbackState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ThrowingA::class)
            ->allowTransition(ThrowingA::class, ThrowingB::class)
            ->onExit(ThrowingA::class, function () {
                throw new \RuntimeException('oops');
            });
    }
}

class ThrowingA extends ThrowingCallbackState
{
}

class ThrowingB extends ThrowingCallbackState
{
}
