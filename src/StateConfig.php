<?php

namespace Crumbls\StateMachine;

use Crumbls\StateMachine\Exceptions\StateConfigurationException;

class StateConfig
{
    /** @var class-string<State>|null */
    protected ?string $defaultState = null;
    /** @var array<class-string<State>, array<class-string<State>>> */
    protected array $allowedTransitions = [];
    /** @var array<string, callable> */
    protected array $guards = [];
    /** @var array<string, array<callable>> */
    protected array $callbacks = [];
    /** @var array<string|object> */
    protected array $middleware = [];

    /**
     * @param class-string<State> $stateClass
     */
    public function default(string $stateClass): static
    {
        $this->defaultState = $stateClass;
        return $this;
    }

    /**
     * @param class-string<State> $from
     * @param class-string<State> $to
     */
    public function allowTransition(string $from, string $to): static
    {
        if (!isset($this->allowedTransitions[$from])) {
            $this->allowedTransitions[$from] = [];
        }
        
        if (in_array($to, $this->allowedTransitions[$from], true)) {
            throw StateConfigurationException::duplicateTransition($from, $to);
        }
        
        $this->allowedTransitions[$from][] = $to;
        return $this;
    }

    public function allowTransitions(string $from, array $toStates): static
    {
        foreach ($toStates as $to) {
            $this->allowTransition($from, $to);
        }
        return $this;
    }

    public function guard(string $from, string $to, callable $guard): static
    {
        $key = $from . '::' . $to;
        $this->guards[$key] = $guard;
        return $this;
    }

    public function onTransition(string $from, string $to, callable $callback): static
    {
        $key = $from . '::' . $to;
        if (!isset($this->callbacks[$key])) {
            $this->callbacks[$key] = [];
        }
        $this->callbacks[$key][] = $callback;
        return $this;
    }

    public function onEnter(string $state, callable $callback): static
    {
        $key = 'enter::' . $state;
        if (!isset($this->callbacks[$key])) {
            $this->callbacks[$key] = [];
        }
        $this->callbacks[$key][] = $callback;
        return $this;
    }

    public function onExit(string $state, callable $callback): static
    {
        $key = 'exit::' . $state;
        if (!isset($this->callbacks[$key])) {
            $this->callbacks[$key] = [];
        }
        $this->callbacks[$key][] = $callback;
        return $this;
    }

    public function getDefaultState(): ?string
    {
        return $this->defaultState;
    }

    public function getAllowedTransitions(): array
    {
        return $this->allowedTransitions;
    }

    public function getGuards(): array
    {
        return $this->guards;
    }

    public function getCallbacks(): array
    {
        return $this->callbacks;
    }

    public function isTransitionAllowed(string $from, string $to): bool
    {
        return isset($this->allowedTransitions[$from]) && 
               in_array($to, $this->allowedTransitions[$from], true);
    }

    public function hasGuard(string $from, string $to): bool
    {
        $key = $from . '::' . $to;
        return isset($this->guards[$key]);
    }

    public function getGuard(string $from, string $to): ?callable
    {
        $key = $from . '::' . $to;
        return $this->guards[$key] ?? null;
    }

    public function getCallbacksFor(string $event, ?string $state = null): array
    {
        $key = $state !== null ? $event . '::' . $state : $event;
        return $this->callbacks[$key] ?? [];
    }

    public function middleware(string|array|object $middleware): static
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
        return $this;
    }

    /**
     * @return array<string|object>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}