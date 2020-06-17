<?php


namespace Codewiser\Workflow;


use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Exceptions\StateMachineConsistencyException;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionPayloadException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StateMachineEngine
{
    /**
     * @var Model|Workflow
     */
    protected $model;

    /**
     * Attribute name. It keeps workflow state
     * @var string
     */
    protected $attribute;

    /**
     * @var WorkflowBlueprint
     */
    protected $blueprint;

    public function __construct(WorkflowBlueprint $blueprint, Model $model, string $attribute)
    {
        $this->model = $model;
        $this->blueprint = $blueprint;
        $this->attribute = $attribute;
    }

    public function __toString()
    {
        return $this->getState();
    }

    /**
     * Get State Machine Blueprint
     * @return WorkflowBlueprint
     */
    public function getBlueprint()
    {
        return $this->blueprint;
    }

    /**
     * Get human readable [current or any] state caption
     * @param null|string $state
     * @return array|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public function getStateCaption($state = null)
    {
        $state = $state ?: $this->getState();
        return trans(Str::snake(class_basename($this->blueprint)) . ".states.{$state}");
    }

    /**
     * Array of available Model workflow states
     * @return Collection|string[]
     */
    public function getStates()
    {
        return $this->blueprint->getStates();
    }

    /**
     * Array of allowed transitions between states
     * @return Collection|Transition[]
     */
    public function getTransitions()
    {
        $transitions = $this->blueprint->getTransitions();
        foreach ($transitions as $transition) {
            $transition->inject($this->model, $this->attribute);
        }
        return $transitions;
    }

    /**
     * Possible transitions from current state
     * @return Collection|Transition[]
     */
    public function getRelevantTransitions()
    {
        $state = $this->getState();
        $transitions = new Collection();
        foreach ($this->getTransitions() as $transition) {
            if ($state == $transition->getSource()) {
                try {
                    $transition->validate();
                } catch (TransitionFatalException $e) {
                    // Transition is irrelevant due to business logic
                    continue;
                } catch (TransitionRecoverableException $e) {
                    // User may resolve issues
                }
                $transitions->push($transition);
            }
        }
        return $transitions;
    }

    /**
     * Search for transition from current model state to given
     * @param string $target
     * @return Transition|null
     */
    protected function findTransitionTo($target)
    {
        foreach ($this->getRelevantTransitions() as $transition) {
            if ($transition->getTarget() == $target) {
                // We found transition from source to target
                return $transition;
            }
        }
        return null;
    }

    /**
     * Find transition
     * @param $source
     * @param $target
     * @return Transition|null
     */
    public function findTransition($source, $target)
    {
        foreach ($this->getTransitions() as $transition) {
            if ($transition->getSource() == $source && $transition->getTarget() == $target) {
                return $transition;
            }
        }
        return null;
    }

    /**
     * Workflow attribute name
     * @return string
     */
    public function getAttributeName()
    {
        return $this->attribute;
    }

    /**
     * Workflow initial state
     * @return string
     */
    public function getInitialState()
    {
        return $this->getStates()->first();
    }

    /**
     * Model current state
     * @return string
     */
    public function getState()
    {
        return $this->model->getAttribute($this->getAttributeName());
    }

    /**
     * Initialize workflow (setting initial state) of the model
     * @return Workflow|Model
     */
    public function init()
    {
        $this->model->setAttribute($this->getAttributeName(), $this->getInitialState());
        return $this->model;
    }

    /**
     * Perform transition to $target state
     * @param string $target target state
     * @param array $payload optional user payload
     * @return boolean transited or not
     * @throws StateMachineConsistencyException impossible transition
     * @throws TransitionException
     * @throws TransitionPayloadException missing required payload data
     * @throws WorkflowException
     */
    public function transit($target, $payload = [])
    {
        if ($transition = $this->findTransitionTo($target)) {
            $transition->validate();
            foreach ($transition->getRequirements() as $attribute) {
                if (!isset($payload[$attribute])) {
                    throw new TransitionPayloadException("Transition requires additional data [{$attribute}]");
                }
            }
        } else {
            throw new StateMachineConsistencyException("There is no transition from `{$this->getState()}` to `{$target}`");
        }

        $source = $this->model->getAttribute($this->getAttributeName());
        $this->model->setAttribute($this->getAttributeName(), $target);

        if ($this->model->fireTransitionEvent('transiting', true, $this, $transition, $payload) === false) {
            return false;
        }

        // Direct change of workflow state is prohibited
        $class = get_class($this->model);
        $class::withoutEvents(function () use ($target) {
            $this->model->save();
        });

        $this->model->fireTransitionEvent('transited', false, $this, $transition, $payload);

        // Fire our event
        event(new ModelTransited($this->model, $this, $transition, $payload));

        return true;
    }

    /**
     * Alias for transit
     */
    public function setState($state, $payload = [])
    {
        return $this->transit($state, $payload);
    }

    /**
     * Rollback workflow state to initial state
     */
    public function reset()
    {
        $this->model->setAttribute($this->getAttributeName(), $this->getInitialState());
    }
}