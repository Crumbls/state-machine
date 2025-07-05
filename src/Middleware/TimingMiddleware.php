<?php

namespace Crumbls\StateMachine\Middleware;

use Crumbls\StateMachine\Http\StateTransitionRequest;
use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\State;
use Crumbls\StateMachine\Exceptions\TimingException;
use Illuminate\Support\Facades\Log;
use Closure;

class TimingMiddleware
{
    public function __construct(
        protected ?int $maxExecutionTimeMs = null,
        protected bool $logSlowTransitions = true,
        protected int $slowThresholdMs = 1000
    ) {}

    public function handle(StateTransitionRequest $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            $result = $next($request);

            $this->logMetrics($request->currentState, $request->toState, $startTime, $startMemory, null);

            return $result;

        } catch (\Exception $e) {
            $this->logMetrics($request->currentState, $request->toState, $startTime, $startMemory, $e);
            throw $e;
        }
    }

    protected function logMetrics(
        State $currentState,
        string $toState,
        float $startTime,
        int $startMemory,
        ?\Exception $exception
    ): void {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed = memory_get_usage(true) - $startMemory;
        $peakMemory = memory_get_peak_usage(true);

        $logData = [
            'from_state' => $currentState->getName(),
            'to_state' => $toState,
            'duration_ms' => $duration,
            'memory_used_bytes' => $memoryUsed,
            'peak_memory_bytes' => $peakMemory,
            'success' => $exception === null
        ];

        if ($exception !== null) {
            $logData['error'] = $exception->getMessage();
            $logData['exception_class'] = get_class($exception);
        }

        // Check for performance issues
        if ($this->maxExecutionTimeMs !== null && $duration > $this->maxExecutionTimeMs) {
            Log::error('State transition exceeded maximum execution time', array_merge($logData, [
                'max_allowed_ms' => $this->maxExecutionTimeMs
            ]));

            if ($exception === null) {
                throw new TimingException(
                    "State transition from {$currentState->getName()} to {$toState} " .
                    "took {$duration}ms, exceeding maximum of {$this->maxExecutionTimeMs}ms"
                );
            }
        }

        if ($this->logSlowTransitions && $duration > $this->slowThresholdMs) {
            Log::warning('Slow state transition detected', array_merge($logData, [
                'slow_threshold_ms' => $this->slowThresholdMs
            ]));
        } else {
            Log::debug('State transition performance metrics', $logData);
        }
    }

    public static function maxExecutionTime(int $milliseconds): static
    {
        return new static($milliseconds);
    }

    public static function withSlowDetection(int $thresholdMs = 1000): static
    {
        return new static(null, true, $thresholdMs);
    }
}