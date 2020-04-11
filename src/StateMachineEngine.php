<?php


namespace Codewiser\Workflow;


use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Exceptions\StateMachineConsistencyException;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionMotivationException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
        return $this->getStateCaption();
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
        return trans("workflow." . class_basename($this->blueprint) . ".states.{$state}");
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
     * @return Transition[]|Collection
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
     * @return Transition[]|Collection
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
     * @param string|null $comment optional user comment
     * @return Workflow|Model
     * @throws StateMachineConsistencyException
     * @throws TransitionException
     * @throws TransitionMotivationException
     * @throws WorkflowException
     */
    public function transit($target, $comment = null)
    {
        if ($this->model->isDirty()) {
            throw new WorkflowException("Model shouldn't be dirty then you call transit method.");
        }
        if ($transition = $this->findTransitionTo($target)) {
            $transition->validate();
            if ($transition instanceof MotivatedTransition && !$comment) {
                throw new TransitionMotivationException("Transition requires user comment");
            }
        } else {
            throw new StateMachineConsistencyException("There is no transition from `{$this->getState()}` to `{$target}`");
        }

        $class = get_class($this->model);
        $this->model->setAttribute($this->getAttributeName(), $target);

        // Will not fire any eloquent events
        $class::withoutEvents(function () use ($target) {
            $this->model->save();
        });

        // Fire our event
        event(new ModelTransited($this->model, $this->getAttributeName(), $target, $comment));

        return $this->model;
    }
}