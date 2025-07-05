<?php

namespace Crumbls\StateMachine;

abstract class State
{
    protected StateMachine $stateMachine;
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(StateMachine $stateMachine, array $context = [])
    {
        $this->stateMachine = $stateMachine;
        $this->context = $context;
    }

    public static function config(): StateConfig
    {
        return new StateConfig();
    }

    public function getName(): string
    {
        return static::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function mergeContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    public function canTransitionTo(string $stateClass): bool
    {
        return $this->stateMachine->canTransitionTo($stateClass);
    }

    public function transitionTo(string $stateClass, array $context = []): State
    {
        return $this->stateMachine->transitionTo($stateClass, $context);
    }

    public function onEnter(): void
    {
        //
    }

    public function onExit(): void
    {
        //
    }
}