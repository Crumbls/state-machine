<?php

namespace Crumbls\StateMachine\Tests\ExampleStates;

class Delivered extends OrderState
{
    public function color(): string
    {
        return 'green';
    }
}