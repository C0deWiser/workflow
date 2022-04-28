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
    public function from(string $state): self
    {
        return $this->filter(function (Transition $transition) use ($state) {
            return $transition->source() == $state;
        });
    }

    /**
     * Transitions to given state.
     */
    public function to(string $state): self
    {
        return $this->filter(function (Transition $transition) use ($state) {
            return $transition->target() == $state;
        });
    }

    /**
     * Transitions without fatal conditions.
     */
    public function withoutForbidden(): self
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
    public function withoutRecoverable(): self
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
    public function authorized(): self
    {
        return $this->filter(function (Transition $transition) {
            if ($ability = $transition->authorization()) {
                if (is_string($ability)) {
                    return Gate::allows($ability, $transition->model());
                } elseif (is_callable($ability)) {
                    return call_user_func($ability, $transition->model());
                }
            }
            return true;
        });
    }
}
