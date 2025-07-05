<?php

namespace Crumbls\StateMachine\Facades;

use Illuminate\Support\Facades\Facade;

class StateMachine extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Crumbls\StateMachine\StateMachineManager::class;
    }
}