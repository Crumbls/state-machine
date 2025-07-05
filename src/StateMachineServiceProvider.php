<?php

namespace Crumbls\StateMachine;

use Illuminate\Support\ServiceProvider;

class StateMachineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StateMachineManager::class, function ($app) {
            return new StateMachineManager();
        });
        
        $this->app->alias(StateMachineManager::class, 'state-machine');
    }

    public function boot(): void
    {
        //
    }
}