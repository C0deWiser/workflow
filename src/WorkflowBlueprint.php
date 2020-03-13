<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Exceptions\WorkflowConsistencyException;
use Codewiser\Workflow\Exceptions\WorkflowInvalidTransitionException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class WorkflowBlueprint
{
    /**
     * @var Model|Workflow
     */
    protected $model;

    /**
     * Attribute name. It keeps workflow state
     */
    protected $attribute;

    /**
     * Array of available Model Workflow steps. First one is initial
     * @return array|string[]
     * @example [new, review, published, correcting]
     */
    abstract protected function states(): array;

    /**
     * Array of allowed transitions between states
     * @return array|Transition[]
     * @example [[new, review], [review, published], [review, correcting], [correcting, review]]
     */
    abstract protected function transitions(): array;

    /**
     * Array of available Model workflow states
     * @return Collection|string[]
     */
    public function getStates()
    {
        return new Collection($this->states());
    }

    /**
     * Array of allowed transitions between states
     * @return Transition[]|Collection
     */
    public function getTransitions()
    {
        $transitions = new Collection();
        foreach ($this->transitions() as $transition) {
            $transition->inject($this->model, $this->attribute);
            $transitions->push($transition);
        }
        return $transitions;
    }

    public function __construct(Model $model, string $attribute)
    {
        $this->model = $model;
        $this->attribute = $attribute;
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
                $transitions->push($transition);
            }
        }
        return $transitions;
    }

    /**
     * Searches transition from current model state to given
     * @param string $target
     * @return Transition|null
     */
    public function findTransitionTo($target)
    {
        foreach ($this->getRelevantTransitions() as $transition) {
            if ($transition->getTarget() == $target) {
                // We found transition from source to target
                return $transition;
            }
        }
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
     * Perform transition to $target state, logging in Journal with $comment
     * @param string $target
     * @return Workflow|Model
     * @throws WorkflowInvalidTransitionException
     * @throws WorkflowConsistencyException
     */
    public function transit($target)
    {
        if ($transition = $this->findTransitionTo($target)) {
            $transition->validate();
        } else {
            throw new WorkflowConsistencyException("There is no transition from `{$this->getState()}` to `{$target}`");
        }

        $this->model->setAttribute($this->getAttributeName(), $target);

        $this->model->journalise('transited', [$this->getAttributeName() => $target]);

        return $this->model;
    }
}