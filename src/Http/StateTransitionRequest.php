<?php

namespace Crumbls\StateMachine\Http;

use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\State;

/**
 * Request object for state transitions that can be passed through Laravel middleware
 */
class StateTransitionRequest
{
    public function __construct(
        public StateMachine $machine,
        public State $currentState,
        public string $toState,
        public array $context = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function mergeContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }
}