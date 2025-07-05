<?php

namespace Crumbls\StateMachine\Events;

use Crumbls\StateMachine\StateMachine;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AsyncTransitionCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public StateMachine $stateMachine,
        public string $fromState,
        public string $toState,
        public ?string $identifier = null,
        public array $context = []
    ) {}
}