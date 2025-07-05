<?php

namespace Crumbls\StateMachine\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AsyncTransitionFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $machineData,
        public string $toState,
        public \Exception $exception,
        public ?string $identifier = null,
        public array $context = []
    ) {}
}