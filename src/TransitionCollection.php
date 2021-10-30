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
     * 
     * @return $this
     */
    public function from(string $state): TransitionCollection
    {
        return $this->filter(function(Transition $transition) use ($state) {
            return $transition->source() == $state;
        });
    }

    /**
     * Transitions to given state.
     * 
     * @return $this
     */
    public function to(string $state): TransitionCollection
    {
        return $this->filter(function(Transition $transition) use ($state) {
            return $transition->target() == $state;
        });
    }

    /**
     * Transitions without fatal conditions.
     * 
     * @return $this
     */
    public function withoutForbidden(): TransitionCollection
    {
        return $this->reject(function(Transition $transition) {
            try {
                $transition->validate();
            } catch (TransitionFatalException $e) {
                return true;
            } catch (TransitionRecoverableException $e) {

            }
            return false;
        });
    }

    /**
     * Blind (forbidden) transitions.
     * 
     * @return $this
     */
    public function withoutRecoverable(): TransitionCollection
    {
        return $this->filter(function(Transition $transition) {
            try {
                $transition->validate();
            } catch (TransitionFatalException $e) {

            } catch (TransitionRecoverableException $e) {
                return true;
            }
            return false;
        });
    }

    /**
     * Authorized transitions.
     * 
     * @return $this
     */
    public function authorized(): TransitionCollection
    {
        return $this->filter(function(Transition $transition) {
            if ($ability = $transition->authorization()) {

                if (is_string($ability)) {
                    return Gate::allows($ability, $transition->model());
                }
                if ($ability instanceof \Closure) {
                    return call_user_func($ability, $transition->model());
                }
            }
            return true;
        });
    }
}