<?php

namespace Crumbls\StateMachine;

use Crumbls\StateMachine\Exceptions\StateConfigurationException;

class StateConfig
{
    /** @var class-string<State>|null */
    protected ?string $defaultState = null;
    /** @var array<class-string<State>, array<class-string<State>>> */
    protected array $allowedTransitions = [];
    /** @var array<class-string<State>, class-string<State>> */
    protected array $preferredTransitions = [];
    /** @var array<class-string<State>, class-string<State>> */
    protected array $rollbackTransitions = [];
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

    /**
     * Mark a transition as the preferred next state for auto-progression.
     *
     * @param class-string<State> $from
     * @param class-string<State> $to
     */
    public function preferredTransition(string $from, string $to): static
    {
        $this->preferredTransitions[$from] = $to;
        return $this;
    }

    /**
     * Mark a transition as the rollback target for the given state.
     *
     * @param class-string<State> $from
     * @param class-string<State> $to
     */
    public function rollbackTransition(string $from, string $to): static
    {
        $this->rollbackTransitions[$from] = $to;
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

    /**
     * @return array<class-string<State>, class-string<State>>
     */
    public function getPreferredTransitions(): array
    {
        return $this->preferredTransitions;
    }

    public function getPreferredTransition(string $from): ?string
    {
        return $this->preferredTransitions[$from] ?? null;
    }

    /**
     * @return array<class-string<State>, class-string<State>>
     */
    public function getRollbackTransitions(): array
    {
        return $this->rollbackTransitions;
    }

    public function getRollbackTransition(string $from): ?string
    {
        return $this->rollbackTransitions[$from] ?? null;
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

    /**
     * Lint the configuration. Returns a list of warning messages describing
     * inconsistencies in the state graph. An empty array means the graph
     * is internally consistent.
     *
     * Detects:
     * - default state not present in any allowed-from list (likely terminal-only)
     * - preferred transitions that aren't in allowedTransitions
     * - rollback transitions that aren't in allowedTransitions
     * - guards/callbacks registered for a from->to pair that isn't allowed
     * - states reachable as a transition target but never used as a from
     *   (informational; terminal states are normal)
     * - states that are unreachable from the default state via allowed
     *   transitions (BFS)
     *
     * @return array<int, string>
     */
    public function validate(): array
    {
        $warnings = [];

        $allFromStates = array_keys($this->allowedTransitions);
        $allToStates = [];
        foreach ($this->allowedTransitions as $targets) {
            foreach ($targets as $target) {
                $allToStates[] = $target;
            }
        }
        $allStates = array_values(array_unique(array_merge($allFromStates, $allToStates)));

        if ($this->defaultState !== null && !in_array($this->defaultState, $allStates, true)) {
            $warnings[] = "Default state {$this->defaultState} is not referenced by any transition.";
        }

        foreach ($this->preferredTransitions as $from => $to) {
            if (!$this->isTransitionAllowed($from, $to)) {
                $warnings[] = "Preferred transition {$from} -> {$to} is not in allowedTransitions.";
            }
        }

        foreach ($this->rollbackTransitions as $from => $to) {
            if (!$this->isTransitionAllowed($from, $to)) {
                $warnings[] = "Rollback transition {$from} -> {$to} is not in allowedTransitions.";
            }
        }

        foreach (array_keys($this->guards) as $key) {
            [$from, $to] = explode('::', $key, 2);
            if (!$this->isTransitionAllowed($from, $to)) {
                $warnings[] = "Guard registered for unreachable transition {$from} -> {$to}.";
            }
        }

        foreach (array_keys($this->callbacks) as $key) {
            if (!str_contains($key, '::')) {
                continue;
            }
            [$prefix, $state] = explode('::', $key, 2);
            if ($prefix === 'enter' || $prefix === 'exit') {
                if (!in_array($state, $allStates, true)) {
                    $warnings[] = "Callback registered for unknown state {$state} ({$prefix}).";
                }
                continue;
            }
            if (!$this->isTransitionAllowed($prefix, $state)) {
                $warnings[] = "Callback registered for unreachable transition {$prefix} -> {$state}.";
            }
        }

        if ($this->defaultState !== null && in_array($this->defaultState, $allStates, true)) {
            $reachable = $this->reachableFrom($this->defaultState);
            foreach ($allStates as $state) {
                if (!in_array($state, $reachable, true)) {
                    $warnings[] = "State {$state} is unreachable from default state {$this->defaultState}.";
                }
            }
        }

        return $warnings;
    }

    /**
     * @return array<int, class-string<State>>
     */
    protected function reachableFrom(string $start): array
    {
        $visited = [$start];
        $queue = [$start];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($this->allowedTransitions[$current] ?? [] as $next) {
                if (!in_array($next, $visited, true)) {
                    $visited[] = $next;
                    $queue[] = $next;
                }
            }
        }

        return $visited;
    }
}