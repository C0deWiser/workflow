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
    public function valid(): TransitionCollection
    {
        return $this->filter(function(Transition $transition) {
            try {
                $transition->validate();
            } catch (TransitionFatalException $e) {
                // Transition is irrelevant due to business logic
                return false;
            } catch (TransitionRecoverableException $e) {
                // User may resolve issues
            }
            return true;
        });
    }

    /**
     * Transitions without recoverable conditions.
     * 
     * @return $this
     */
    public function allowed(): TransitionCollection
    {
        return $this->filter(function(Transition $transition) {
            try {
                $transition->validate();
            } catch (TransitionFatalException $e) {
                // Transition is irrelevant due to business logic
            } catch (TransitionRecoverableException $e) {
                // User may resolve issues
                return false;
            }
            return true;
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