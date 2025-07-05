<?php

namespace Crumbls\StateMachine\Middleware;

use Crumbls\StateMachine\Http\StateTransitionRequest;
use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\State;
use Illuminate\Support\Facades\Log;
use Closure;

class LoggingMiddleware
{
    public function __construct(
        protected string $logLevel = 'info',
        protected bool $logContext = true,
        protected bool $logTimings = true
    ) {}

    public function handle(StateTransitionRequest $request, Closure $next)
    {
        $startTime = microtime(true);
        $fromState = $request->currentState->getName();

        $logData = [
            'from_state' => $fromState,
            'to_state' => $request->toState,
        ];

        if ($this->logContext) {
            $logData['context'] = $request->context;
        }

        Log::log($this->logLevel, "State transition starting: {$fromState} → {$request->toState}", $logData);

        try {
            $result = $next($request);

            if ($this->logTimings) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $logData['duration_ms'] = $duration;
            }

            Log::log($this->logLevel, "State transition completed: {$fromState} → {$request->toState}", $logData);

            return $result;

        } catch (\Exception $e) {
            if ($this->logTimings) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $logData['duration_ms'] = $duration;
            }

            $logData['error'] = $e->getMessage();
            $logData['exception_class'] = get_class($e);

            Log::error("State transition failed: {$fromState} → {$request->toState}", $logData);

            throw $e;
        }
    }

    public static function withLevel(string $level): static
    {
        return new static($level);
    }

    public static function detailed(): static
    {
        return new static('debug', true, true);
    }

    public static function minimal(): static
    {
        return new static('info', false, false);
    }
}