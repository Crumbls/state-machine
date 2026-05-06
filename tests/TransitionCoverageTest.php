<?php

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\Exceptions\InvalidTransitionException;
use Crumbls\StateMachine\Tests\ExampleStates\OrderState;
use Crumbls\StateMachine\Tests\ExampleStates\Pending;
use Crumbls\StateMachine\Tests\ExampleStates\Processing;
use Crumbls\StateMachine\Tests\ExampleStates\Shipped;
use Crumbls\StateMachine\Tests\ExampleStates\Delivered;
use Crumbls\StateMachine\Tests\ExampleStates\Cancelled;

/**
 * Build the full set of (from, to) pairs across the OrderState graph and assert
 * every pair behaves correctly when executed against a fresh state machine.
 */
$states = [Pending::class, Processing::class, Shipped::class, Delivered::class, Cancelled::class];

$allowed = [
    [Pending::class, Processing::class],
    [Pending::class, Cancelled::class],
    [Processing::class, Shipped::class],
    [Processing::class, Cancelled::class],
    [Shipped::class, Delivered::class],
];

$allowedSet = array_map(fn ($pair) => $pair[0] . '->' . $pair[1], $allowed);

$bringTo = function (string $target): StateMachine {
    $machine = StateMachine::make(OrderState::class);

    if ($target === Pending::class) {
        return $machine;
    }

    $paths = [
        Processing::class => [Processing::class],
        Cancelled::class => [Cancelled::class],
        Shipped::class => [Processing::class, Shipped::class],
        Delivered::class => [Processing::class, Shipped::class, Delivered::class],
    ];

    foreach ($paths[$target] as $step) {
        $machine->transitionTo($step);
    }

    return $machine;
};

foreach ($states as $from) {
    foreach ($states as $to) {
        if ($from === $to) {
            continue;
        }

        $key = $from . '->' . $to;
        $shortFrom = substr(strrchr($from, '\\') ?: $from, 1);
        $shortTo = substr(strrchr($to, '\\') ?: $to, 1);

        if (in_array($key, $allowedSet, true)) {
            it("allows {$shortFrom} -> {$shortTo}", function () use ($bringTo, $from, $to) {
                $machine = $bringTo($from);

                expect($machine->canTransitionTo($to))->toBeTrue();

                $machine->transitionTo($to);

                expect($machine->is($to))->toBeTrue();
                expect($machine->getCurrentStateName())->toBe($to);
            });
        } else {
            it("rejects {$shortFrom} -> {$shortTo}", function () use ($bringTo, $from, $to) {
                $machine = $bringTo($from);

                expect($machine->canTransitionTo($to))->toBeFalse();
                expect(fn () => $machine->transitionTo($to))
                    ->toThrow(InvalidTransitionException::class);
            });
        }
    }
}

it('treats terminal states as having no outgoing transitions', function () use ($bringTo, $states) {
    foreach ([Delivered::class, Cancelled::class] as $terminal) {
        $machine = $bringTo($terminal);

        foreach ($states as $candidate) {
            if ($candidate === $terminal) {
                continue;
            }

            expect($machine->canTransitionTo($candidate))->toBeFalse();
        }
    }
});

it('rejects self-transitions even when the state is reachable', function () use ($bringTo, $states) {
    foreach ($states as $state) {
        $machine = $bringTo($state);

        expect($machine->canTransitionTo($state))->toBeFalse();
        expect(fn () => $machine->transitionTo($state))
            ->toThrow(InvalidTransitionException::class);
    }
});
