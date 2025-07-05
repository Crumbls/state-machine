<?php

namespace Crumbls\StateMachine\Middleware;

use Crumbls\StateMachine\Http\StateTransitionRequest;
use Crumbls\StateMachine\Exceptions\RateLimitExceededException;
use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\State;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Closure;

class RateLimitMiddleware
{
    public function __construct(
        protected int $maxAttempts = 10,
        protected int $decayMinutes = 60,
        protected ?string $keyPrefix = null
    ) {
        $this->keyPrefix = $keyPrefix ?? 'state_machine_rate_limit';
    }

    public function handle(StateTransitionRequest $request, Closure $next)
    {
        $key = $this->getRateLimitKey($request->machine, $request->currentState, $request->toState, $request->context);
        $attempts = Cache::get($key, 0);

        if ($attempts >= $this->maxAttempts) {
            Log::warning('Rate limit exceeded for state transition', [
                'key' => $key,
                'attempts' => $attempts,
                'max_attempts' => $this->maxAttempts,
                'from_state' => $request->currentState->getName(),
                'to_state' => $request->toState,
                'context' => $request->context
            ]);

            throw new RateLimitExceededException(
                "Rate limit exceeded for transition from {$request->currentState->getName()} to {$request->toState}. " .
                "Max {$this->maxAttempts} attempts per {$this->decayMinutes} minutes."
            );
        }

        // Increment attempt counter
        Cache::put($key, $attempts + 1, now()->addMinutes($this->decayMinutes));

        Log::debug('Rate limit check passed', [
            'key' => $key,
            'attempts' => $attempts + 1,
            'max_attempts' => $this->maxAttempts
        ]);

        return $next($request);
    }

    protected function getRateLimitKey(
        StateMachine $machine, 
        State $currentState, 
        string $toState, 
        array $context
    ): string {
        // Create a unique key based on machine context and transition
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

    public static function maxAttempts(int $attempts): static
    {
        return new static($attempts);
    }

    public static function perMinutes(int $attempts, int $minutes): static
    {
        return new static($attempts, $minutes);
    }
}