<?php

namespace Crumbls\StateMachine\Tests\ExampleStates\Workflow;

use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;

abstract class WorkflowState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Review::class)
            ->allowTransition(Review::class, Approved::class)
            ->allowTransition(Review::class, Rejected::class)
            ->allowTransition(Review::class, Draft::class)
            ->allowTransition(Approved::class, Published::class)
            ->allowTransition(Approved::class, Review::class)
            ->allowTransition(Published::class, Approved::class)
            ->preferredTransition(Draft::class, Review::class)
            ->preferredTransition(Review::class, Approved::class)
            ->preferredTransition(Approved::class, Published::class)
            ->rollbackTransition(Review::class, Draft::class)
            ->rollbackTransition(Approved::class, Review::class)
            ->rollbackTransition(Published::class, Approved::class);
    }
}
