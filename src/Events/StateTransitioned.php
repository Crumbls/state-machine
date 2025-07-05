<?php

namespace Crumbls\StateMachine\Events;

use Crumbls\StateMachine\StateMachine;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StateTransitioned
{
    use Dispatchable, SerializesModels;

    public StateMachine $stateMachine;
    public string $fromState;
    public string $toState;
    public array $context;

    public function __construct(StateMachine $stateMachine, string $fromState, string $toState, array $context = [])
    {
        $this->stateMachine = $stateMachine;
        $this->fromState = $fromState;
        $this->toState = $toState;
        $this->context = $context;
    }
}