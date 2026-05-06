<?php

namespace Crumbls\StateMachine\Tests\ExampleStates\Workflow;

class HaltingReview extends ExecutableWorkflowState
{
    public function execute(): bool
    {
        return false;
    }
}
