<?php

namespace Crumbls\StateMachine\Traits;

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\Exceptions\StateSerializationException;

trait HasStateMachine
{
    protected ?StateMachine $stateMachineInstance = null;

    /**
     * Get the state machine column name
     */
    public function getStateMachineColumn(): string
    {
        return $this->stateMachineColumn ?? 'state_machine_data';
    }

    /**
     * Get the state machine class
     */
    abstract public function getStateMachineClass(): string;

    /**
     * Get or create the state machine instance
     */
    public function stateMachine(): StateMachine
    {
        if ($this->stateMachineInstance === null) {
            $this->loadStateMachine();
        }

        return $this->stateMachineInstance;
    }

    /**
     * Load state machine from model data
     */
    protected function loadStateMachine(): void
    {
        $data = $this->getAttribute($this->getStateMachineColumn());
        
        if (empty($data)) {
            // Create new state machine with default state
            $this->stateMachineInstance = StateMachine::make(
                $this->getStateMachineClass(),
                $this->getStateMachineContext()
            );
        } else {
            // Restore from saved data
            $parsedData = is_string($data) ? json_decode($data, true) : $data;
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw StateSerializationException::invalidJson($data);
            }
            
            $this->stateMachineInstance = StateMachine::restore($parsedData);
        }
    }

    /**
     * Save state machine to model
     */
    public function saveStateMachine(): static
    {
        if ($this->stateMachineInstance) {
            $this->setAttribute(
                $this->getStateMachineColumn(),
                $this->stateMachineInstance->toJson()
            );
        }

        return $this;
    }

    /**
     * Get context for state machine initialization
     */
    protected function getStateMachineContext(): array
    {
        return [
            'model_id' => $this->getKey(),
            'model_type' => static::class,
        ];
    }

    /**
     * Transition state and save to model
     */
    public function transitionTo(string $stateClass, array $context = []): \Crumbls\StateMachine\State
    {
        $state = $this->stateMachine()->transitionTo($stateClass, $context);
        $this->saveStateMachine();
        
        return $state;
    }

    /**
     * Async transition with automatic model saving
     */
    public function transitionToAsync(
        string $stateClass,
        array $context = [],
        ?string $queue = null,
        ?int $delay = null
    ): \Illuminate\Foundation\Bus\PendingDispatch {
        // Save current state before async transition
        $this->saveStateMachine();
        
        $identifier = $this->getStateMachineIdentifier();
        
        return $this->stateMachine()->transitionToAsync(
            $stateClass,
            array_merge($context, $this->getStateMachineContext()),
            $identifier,
            $queue,
            $delay
        );
    }

    /**
     * Async transition with continuation and automatic model saving
     */
    public function transitionToAsyncWithContinuation(
        string $stateClass,
        array $context = [],
        ?string $queue = null,
        ?int $delay = null
    ): \Illuminate\Foundation\Bus\PendingDispatch {
        // Save current state before async transition
        $this->saveStateMachine();
        
        $identifier = $this->getStateMachineIdentifier();
        
        // Note: Callbacks in async context cannot access $this model instance
        // Users should handle model persistence in their own callbacks if needed
        
        return $this->stateMachine()->transitionToAsyncWithContinuation(
            $stateClass,
            array_merge($context, $this->getStateMachineContext()),
            $identifier,
            $queue,
            $delay
        );
    }

    /**
     * Get unique identifier for this model's state machine
     */
    protected function getStateMachineIdentifier(): string
    {
        return static::class . ':' . $this->getKey();
    }

    /**
     * Check if model is in specific state
     */
    public function isInState(string $stateClass): bool
    {
        return $this->stateMachine()->is($stateClass);
    }

    /**
     * Get current state
     */
    public function getCurrentState(): \Crumbls\StateMachine\State
    {
        return $this->stateMachine()->getCurrentState();
    }

    /**
     * Get current state name
     */
    public function getCurrentStateName(): string
    {
        return $this->stateMachine()->getCurrentStateName();
    }

    /**
     * Check if transition is allowed
     */
    public function canTransitionTo(string $stateClass): bool
    {
        return $this->stateMachine()->canTransitionTo($stateClass);
    }
}