<?php

namespace Crumbls\StateMachine\Tests\ExampleStates;

class Cancelled extends OrderState
{
    public function color(): string
    {
        return 'red';
    }
}