<?php

namespace Codewiser\Workflow;

use Illuminate\Support\Collection;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Illuminate\Support\Facades\Gate;

/**
 * @method Transition first(callable $callback = null, $default = null)
 * @method Transition sole($key = null, $operator = null, $value = null)
 */
class TransitionCollection extends Collection
{
    /**
     * Transitions from given state.
     */
    public function from(State|string|int $state): static
    {
        return $this->filter(function (Transition $transition) use ($state) {
            return $transition->source()->is($state);
        });
    }

    /**
     * Transitions to given state.
     */
    public function to(State|string|int $state): static
    {
        return $this->filter(function (Transition $transition) use ($state) {
            return $transition->target()->is($state);
        });
    }

    /**
     * Transitions without fatal conditions.
     */
    public function withoutForbidden(): static
    {
        return $this->reject(function (Transition $transition) {
            try {
                $transition->validate();
            } catch (TransitionFatalException) {
                return true;
            } catch (TransitionRecoverableException) {

            }
            return false;
        });
    }

    /**
     * Blind (forbidden) transitions.
     */
    public function withoutRecoverable(): static
    {
        return $this->filter(function (Transition $transition) {
            try {
                $transition->validate();
            } catch (TransitionFatalException) {

            } catch (TransitionRecoverableException) {
                return true;
            }
            return false;
        });
    }

    /**
     * Authorized transitions.
     */
    public function authorized(): static
    {
        return $this->filter(function (Transition $transition) {
            if ($ability = $transition->authorization()) {
                if (is_string($ability)) {
                    return Gate::allows($ability, $transition->engine()->model());
                } elseif (is_callable($ability)) {
                    return call_user_func($ability, $transition->engine()->model());
                }
            }
            return true;
        });
    }
}
