<?php

namespace Crumbls\StateMachine\Jobs;

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\Events\AsyncTransitionCompleted;
use Crumbls\StateMachine\Events\AsyncTransitionFailed;
use Crumbls\StateMachine\Exceptions\InvalidTransitionException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class ProcessStateTransition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public array $machineData,
        public string $toState,
        public array $context = [],
        public ?string $identifier = null,
        public bool $continueOnSuccess = false
    ) {}

    public function handle(): void
    {
        try {
            Log::debug('Processing async state transition', [
                'identifier' => $this->identifier,
                'to_state' => $this->toState,
                'context' => $this->context
            ]);

            // Restore state machine
            $machine = StateMachine::restore($this->machineData);
            $fromState = $machine->getCurrentStateName();

            // Perform transition
            $machine->transitionTo($this->toState, $this->context);

            // Fire completion event
            Event::dispatch(new AsyncTransitionCompleted(
                $machine,
                $fromState,
                $this->toState,
                $this->identifier,
                $this->context
            ));

            // Continue to next state if configured
            if ($this->continueOnSuccess) {
                $this->dispatchNextTransition($machine);
            }

            Log::info('Async state transition completed', [
                'identifier' => $this->identifier,
                'from_state' => $fromState,
                'to_state' => $this->toState
            ]);

        } catch (\Exception $e) {
            Log::error('Async state transition failed', [
                'identifier' => $this->identifier,
                'to_state' => $this->toState,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fire failure event
            Event::dispatch(new AsyncTransitionFailed(
                $this->machineData,
                $this->toState,
                $e,
                $this->identifier,
                $this->context
            ));

            throw $e;
        }
    }

    protected function dispatchNextTransition(StateMachine $machine): void
    {
        // Get possible next states from current state
        $currentState = $machine->getCurrentStateName();
        $allowedTransitions = $machine->getConfig()->getAllowedTransitions();

        if (isset($allowedTransitions[$currentState])) {
            $nextStates = $allowedTransitions[$currentState];
            
            // If there's only one possible next state, auto-transition
            if (count($nextStates) === 1) {
                $nextState = $nextStates[0];
                
                // Check if transition is allowed (guards, etc.)
                if ($machine->canTransitionTo($nextState)) {
                    Log::debug('Auto-continuing to next state', [
                        'identifier' => $this->identifier,
                        'current_state' => $currentState,
                        'next_state' => $nextState
                    ]);

                    // Dispatch next transition
                    static::dispatch(
                        $machine->toArray(),
                        $nextState,
                        [],
                        $this->identifier,
                        $this->continueOnSuccess
                    );
                }
            }
        }
    }
}