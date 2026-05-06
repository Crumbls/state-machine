<?php

namespace Crumbls\StateMachine\Tests\ExampleStates\Workflow;

use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;

abstract class ExecutableWorkflowState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ExecutableDraft::class)
            ->allowTransition(ExecutableDraft::class, HaltingReview::class)
            ->allowTransition(HaltingReview::class, ExecutableApproved::class)
            ->preferredTransition(ExecutableDraft::class, HaltingReview::class)
            ->preferredTransition(HaltingReview::class, ExecutableApproved::class);
    }
}
