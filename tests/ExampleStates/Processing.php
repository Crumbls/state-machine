<?php

namespace Crumbls\StateMachine\Tests\ExampleStates;

class Processing extends OrderState
{
    public function color(): string
    {
        return 'blue';
    }
}