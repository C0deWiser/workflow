<?php

namespace Codewiser\Workflow;

use Illuminate\Support\Collection;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Illuminate\Support\Facades\Gate;

/**
 * @method Transition first()
 * @method Transition sole()
 */
class TransitionCollection extends Collection
{
    /**
     * Transitions from given state.
     * 
     * @return $this
     */
    public function goingFrom(string $state)
    {
        return $this->filter(function(Transition $transition) use ($state) {
            return $transition->getSource() == $state;
        });
    }

    /**
     * Transitions to given state.
     * 
     * @return $this
     */
    public function goingTo(string $state)
    {
        return $this->filter(function(Transition $transition) use ($state) {
            return $transition->getTarget() == $state;
        });
    }

    /**
     * Transitions without fatal incoditions.
     * 
     * @return $this
     */
    public function valid()
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
     * Transitions without recovarable incoditions.
     * 
     * @return $this
     */
    public function allowed()
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
    public function authorized()
    {
        return $this->filter(function(Transition $transition) {
            if ($ability = $transition->getAbility()) {
                return Gate::allows($ability, $transition->model);
            }
            return true;
        });
    }
}