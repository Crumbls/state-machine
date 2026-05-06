<?php

namespace Crumbls\StateMachine\Tests\ExampleStates\Workflow;

use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;

abstract class LoopingState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(LoopA::class)
            ->allowTransition(LoopA::class, LoopB::class)
            ->allowTransition(LoopB::class, LoopA::class)
            ->preferredTransition(LoopA::class, LoopB::class)
            ->preferredTransition(LoopB::class, LoopA::class);
    }
}
