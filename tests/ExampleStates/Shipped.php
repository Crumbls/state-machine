<?php

namespace Crumbls\StateMachine\Tests\ExampleStates;

class Shipped extends OrderState
{
    public function color(): string
    {
        return 'orange';
    }
}