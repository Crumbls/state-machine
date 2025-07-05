<?php

namespace Crumbls\StateMachine\Tests\ExampleStates;

use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;

abstract class OrderState extends State
{
    abstract public function color(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Processing::class)
            ->allowTransition(Processing::class, Shipped::class)
            ->allowTransition(Processing::class, Cancelled::class)
            ->allowTransition(Shipped::class, Delivered::class)
            ->allowTransition(Pending::class, Cancelled::class);
    }
}