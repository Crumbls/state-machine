<?php

namespace Crumbls\StateMachine\Tests;

use Crumbls\StateMachine\StateMachineServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            StateMachineServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'StateMachine' => \Crumbls\StateMachine\Facades\StateMachine::class,
        ];
    }
}