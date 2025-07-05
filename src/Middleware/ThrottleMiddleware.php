<?php

namespace Crumbls\StateMachine\Middleware;

use Crumbls\StateMachine\Http\StateTransitionRequest;
use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\State;
use Crumbls\StateMachine\Exceptions\ThrottleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Closure;

class ThrottleMiddleware
{
    public function __construct(
        protected int $maxAttempts = 5,
        protected int $decaySeconds = 60,
        protected ?string $keyPrefix = null
    ) {
        $this->keyPrefix = $keyPrefix ?? 'state_machine_throttle';
    }

    public function handle(StateTransitionRequest $request, Closure $next)
    {
        $key = $this->getThrottleKey($request->machine, $request->currentState, $request->toState, $request->context);
        $lockKey = $key . ':lock';

        // Check if currently locked
        if (Cache::has($lockKey)) {
            $lockInfo = Cache::get($lockKey);
            throw new ThrottleException(
                "Transition from {$request->currentState->getName()} to {$request->toState} is currently throttled. " .
                "Lock expires at {$lockInfo['expires_at']}"
            );
        }

        $attempts = Cache::get($key, 0);

        if ($attempts >= $this->maxAttempts) {
            // Create lock
            $expiresAt = now()->addSeconds($this->decaySeconds);
            Cache::put($lockKey, [
                'locked_at' => now()->toISOString(),
                'expires_at' => $expiresAt->toISOString(),
                'attempts' => $attempts
            ], $this->decaySeconds);

            Log::warning('State transition throttled', [
                'key' => $key,
                'attempts' => $attempts,
                'max_attempts' => $this->maxAttempts,
                'locked_until' => $expiresAt->toISOString()
            ]);

            throw new ThrottleException(
                "Too many transition attempts from {$request->currentState->getName()} to {$request->toState}. " .
                "Throttled until {$expiresAt->toDateTimeString()}"
            );
        }

        // Increment attempt counter
        Cache::put($key, $attempts + 1, now()->addSeconds($this->decaySeconds));

        try {
            $result = $next($request);
            
            // Reset counter on successful transition
            Cache::forget($key);
            
            return $result;
            
        } catch (\Exception $e) {
            // Keep the counter for failures
            Log::debug('Failed transition counted for throttling', [
                'key' => $key,
                'attempts' => $attempts + 1,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    protected function getThrottleKey(
        StateMachine $machine, 
        State $currentState, 
        string $toState, 
        array $context
    ): string {
        $identifier = $context['model_id'] ?? 'unknown';
        $modelType = $context['model_type'] ?? 'unknown';
        
        return sprintf(
            '%s:%s:%s:%s:%s',
            $this->keyPrefix,
            $modelType,
            $identifier,
            $currentState->getName(),
            $toState
        );
    }

    public static function attempts(int $maxAttempts, int $decaySeconds = 60): static
    {
        return new static($maxAttempts, $decaySeconds);
    }
}