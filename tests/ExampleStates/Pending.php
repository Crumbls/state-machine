<?php

namespace Crumbls\StateMachine\Tests\ExampleStates;

class Pending extends OrderState
{
    public function color(): string
    {
        return 'yellow';
    }
}