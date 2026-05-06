<?php

declare(strict_types=1);

namespace Crumbls\StateMachine\Concerns;

/**
 * Adds proceed()/rollback()/autoTransition() to a model that already uses the
 * HasStateMachine trait. Each method delegates to the underlying state machine
 * and persists the model after a successful transition.
 *
 * @mixin \Crumbls\StateMachine\Traits\HasStateMachine
 */
trait HasProceedRollback
{
    public function proceed(array $context = []): bool
    {
        if (!$this->stateMachine()->proceed($context)) {
            return false;
        }

        $this->saveStateMachine();

        return true;
    }

    public function rollback(array $context = []): bool
    {
        if (!$this->stateMachine()->rollback($context)) {
            return false;
        }

        $this->saveStateMachine();

        return true;
    }

    public function canProceed(): bool
    {
        return $this->stateMachine()->canProceed();
    }

    public function canRollback(): bool
    {
        return $this->stateMachine()->canRollback();
    }

    /**
     * Walk the preferred-transition chain, persisting after each step.
     *
     * @return array{transition_count: int, final_state_class: class-string<\Crumbls\StateMachine\State>, stopped_reason: string}
     */
    public function autoTransition(int $maxTransitions = 100): array
    {
        $result = $this->stateMachine()->autoTransition($maxTransitions);

        if ($result['transition_count'] > 0) {
            $this->saveStateMachine();
        }

        return $result;
    }
}
