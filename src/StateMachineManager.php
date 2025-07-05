<?php

namespace Crumbls\StateMachine;

class StateMachineManager
{
    protected array $machines = [];

    public function create(string $name, string $stateClass, array $context = []): StateMachine
    {
        $machine = new StateMachine($stateClass, $context);
        $this->machines[$name] = $machine;
        return $machine;
    }

    public function get(string $name): ?StateMachine
    {
        return $this->machines[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->machines[$name]);
    }

    public function remove(string $name): void
    {
        unset($this->machines[$name]);
    }

    public function all(): array
    {
        return $this->machines;
    }

    public function make(string $stateClass, array $context = []): StateMachine
    {
        return new StateMachine($stateClass, $context);
    }
}