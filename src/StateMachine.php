<?php

namespace Crumbls\StateMachine;

use Crumbls\StateMachine\Exceptions\InvalidStateException;
use Crumbls\StateMachine\Exceptions\InvalidTransitionException;
use Crumbls\StateMachine\Exceptions\StateConfigurationException;
use Crumbls\StateMachine\Exceptions\StateSerializationException;
use Crumbls\StateMachine\Exceptions\GuardException;
use Crumbls\StateMachine\Jobs\ProcessStateTransition;
use Crumbls\StateMachine\Pipeline\StateMachinePipeline;
use Crumbls\StateMachine\Http\StateTransitionRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class StateMachine
{
    protected State $currentState;
    protected StateConfig $config;
    protected string $stateClass;
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * @param class-string<State> $stateClass
     * @param array<string, mixed> $context
     */
    public function __construct(string $stateClass, array $context = [])
    {
        $this->validateStateClass($stateClass);

        $this->stateClass = $stateClass;
        $this->context = $context;
        $this->config = $stateClass::config();

        $defaultState = $this->config->getDefaultState();
        if ($defaultState === null) {
            throw StateConfigurationException::noDefaultState($stateClass);
        }

        $this->validateStateClass($defaultState);
        $this->currentState = new $defaultState($this, $context);
    }

    /**
     * @param class-string<State> $stateClass
     * @param array<string, mixed> $context
     */
    public static function make(string $stateClass, array $context = []): self
    {
        return new static($stateClass, $context);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function restore(array $data): self
    {
        $requiredKeys = ['state_class', 'current_state', 'context'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw StateSerializationException::missingKey($key);
            }
        }

        $machine = new static($data['state_class'], $data['context']);
        $machine->validateStateClass($data['current_state']);
        $machine->currentState = new $data['current_state']($machine, $data['context']);
        return $machine;
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw StateSerializationException::invalidJson($json);
        }
        return static::restore($data);
    }

    public function getCurrentState(): State
    {
        return $this->currentState;
    }

    public function getCurrentStateName(): string
    {
        return $this->currentState->getName();
    }

    public function is(string $stateClass): bool
    {
        return $this->currentState instanceof $stateClass;
    }

    public function canTransitionTo(string $stateClass): bool
    {
        $currentStateName = $this->getCurrentStateName();

        if (!$this->config->isTransitionAllowed($currentStateName, $stateClass)) {
            return false;
        }

        if ($this->config->hasGuard($currentStateName, $stateClass)) {
            $guard = $this->config->getGuard($currentStateName, $stateClass);
            if (!is_callable($guard)) {
                throw GuardException::guardNotCallable($currentStateName, $stateClass);
            }
            return $guard($this->currentState, $this->context);
        }

        return true;
    }

    /**
     * @param class-string<State> $stateClass
     * @param array<string, mixed> $context
     */
    public function transitionTo(string $stateClass, array $context = []): State
    {
        $this->validateStateClass($stateClass);

        if (!$this->canTransitionTo($stateClass)) {
            throw new InvalidTransitionException(
                "Cannot transition from {$this->getCurrentStateName()} to {$stateClass}"
            );
        }

        // Execute middleware pipeline
        $middleware = $this->config->getMiddleware();
        if (count($middleware) > 0) {
            $request = new StateTransitionRequest($this, $this->currentState, $stateClass, $context);
            $pipeline = new StateMachinePipeline();

            return $pipeline->send($request)
                ->through($middleware)
                ->then(function (StateTransitionRequest $request) {
                    return $this->executeTransition($request->toState, $request->context);
                });
        }

        return $this->executeTransition($stateClass, $context);
    }

    protected function executeTransition(string $stateClass, array $context = []): State
    {
        $currentStateName = $this->getCurrentStateName();

        Log::debug("State transition starting", [
            'from' => $currentStateName,
            'to' => $stateClass,
            'context' => $context
        ]);

        $this->fireCallbacks('exit', $currentStateName);

        $this->currentState->onExit();

        $this->fireCallbacks($currentStateName, $stateClass);

        $newContext = array_merge($this->context, $context);
        $this->currentState = new $stateClass($this, $newContext);
        $this->context = $newContext;

        $this->currentState->onEnter();

        $this->fireCallbacks('enter', $stateClass);

        Event::dispatch(new Events\StateTransitioned($this, $currentStateName, $stateClass, $context));

        Log::debug("State transition completed", [
            'from' => $currentStateName,
            'to' => $stateClass,
            'final_context' => $this->context
        ]);

        return $this->currentState;
    }

    public function transitionToAsync(
        string $stateClass,
        array $context = [],
        ?string $identifier = null,
        ?string $queue = null,
        ?int $delay = null
    ): \Illuminate\Foundation\Bus\PendingDispatch {
        $this->validateStateClass($stateClass);

        if (!$this->canTransitionTo($stateClass)) {
            throw new InvalidTransitionException(
                "Cannot transition from {$this->getCurrentStateName()} to {$stateClass}"
            );
        }

        $job = ProcessStateTransition::dispatch(
            $this->toArray(),
            $stateClass,
            $context,
            $identifier,
            false
        );

        if ($queue !== null) {
            $job->onQueue($queue);
        }

        if ($delay !== null) {
            $job->delay($delay);
        }

        Log::debug("Async state transition queued", [
            'identifier' => $identifier,
            'from' => $this->getCurrentStateName(),
            'to' => $stateClass,
            'queue' => $queue,
            'delay' => $delay
        ]);

        return $job;
    }

    public function transitionToAsyncWithContinuation(
        string $stateClass,
        array $context = [],
        ?string $identifier = null,
        ?string $queue = null,
        ?int $delay = null
    ): \Illuminate\Foundation\Bus\PendingDispatch {
        $this->validateStateClass($stateClass);

        if (!$this->canTransitionTo($stateClass)) {
            throw new InvalidTransitionException(
                "Cannot transition from {$this->getCurrentStateName()} to {$stateClass}"
            );
        }

        $job = ProcessStateTransition::dispatch(
            $this->toArray(),
            $stateClass,
            $context,
            $identifier,
            true
        );

        if ($queue !== null) {
            $job->onQueue($queue);
        }

        if ($delay !== null) {
            $job->delay($delay);
        }

        Log::debug("Async state transition with continuation queued", [
            'identifier' => $identifier,
            'from' => $this->getCurrentStateName(),
            'to' => $stateClass,
            'queue' => $queue,
            'delay' => $delay,
            'continue_on_success' => true
        ]);

        return $job;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        $this->currentState->setContext($context);
        return $this;
    }

    public function mergeContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        $this->currentState->mergeContext($context);
        return $this;
    }

    public function getConfig(): StateConfig
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state_class' => $this->stateClass,
            'current_state' => $this->getCurrentStateName(),
            'context' => $this->context,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function serialize(): string
    {
        return serialize($this->toArray());
    }

    public static function unserialize(string $data): self
    {
        return static::restore(unserialize($data));
    }

    protected function validateStateClass(string $stateClass): void
    {
        if (!class_exists($stateClass)) {
            throw StateConfigurationException::invalidStateClass($stateClass);
        }

        if (!is_subclass_of($stateClass, State::class)) {
            throw StateConfigurationException::invalidStateClass($stateClass);
        }
    }

    protected function fireCallbacks(string $event, ?string $state = null): void
    {
        $callbacks = $this->config->getCallbacksFor($event, $state);

        foreach ($callbacks as $callback) {
            if (!is_callable($callback)) {
                Log::warning("Non-callable callback found", [
                    'event' => $event,
                    'state' => $state,
                    'callback' => $callback
                ]);
                continue;
            }

            try {
                $callback($this->currentState, $this->context);
            } catch (\Exception $e) {
                Log::error("Callback execution failed", [
                    'event' => $event,
                    'state' => $state,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
    }
}
