<?php

namespace Crumbls\StateMachine\Tests\ExampleStates\Workflow;

use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;

/**
 * Demonstrates a config with a preferred transition that has not been allowed.
 * Used to confirm that preferredTransition() does not implicitly authorize a transition.
 */
abstract class PreferredOnlyState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->preferredTransition(Draft::class, Published::class);
    }
}
