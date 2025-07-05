<?php

namespace Crumbls\StateMachine\Middleware;

use Crumbls\StateMachine\Http\StateTransitionRequest;
use Crumbls\StateMachine\StateMachine;
use Crumbls\StateMachine\State;
use Crumbls\StateMachine\Exceptions\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Closure;

class ValidationMiddleware
{
    public function __construct(
        protected array $rules = [],
        protected array $messages = []
    ) {}

    public function handle(StateTransitionRequest $request, Closure $next)
    {
        if (count($this->rules) === 0) {
            return $next($request);
        }

        $validator = Validator::make($request->context, $this->rules, $this->messages);

        if ($validator->fails()) {
            Log::warning('State transition validation failed', [
                'from_state' => $request->currentState->getName(),
                'to_state' => $request->toState,
                'context' => $request->context,
                'errors' => $validator->errors()->toArray()
            ]);

            throw new ValidationException(
                "Validation failed for transition from {$request->currentState->getName()} to {$request->toState}: " .
                implode(', ', $validator->errors()->all())
            );
        }

        Log::debug('State transition validation passed', [
            'from_state' => $request->currentState->getName(),
            'to_state' => $request->toState,
            'validated_context' => $request->context
        ]);

        return $next($request);
    }

    public static function rules(array $rules, array $messages = []): static
    {
        return new static($rules, $messages);
    }
}