<?php

namespace Codewiser\Workflow;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Codewiser\Workflow\Exceptions\WorkflowException;

abstract class WorkflowBlueprint
{
    /**
     * @var Model
     */
    protected $model;
    /**
     * Attribute name. It keeps workflow state
     */
    const ATTRIBUTE = 'workflow';

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
            $transition->inject($this->model);
            $transitions->push($transition);
        }
        return $transitions;
    }

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Possible transitions from current state
     * @return Transition[]|Collection
     */
    public function getRelevantTransitions()
    {
        $state = $this->model->getAttribute($this->getAttribute());
        $transitions = new Collection();
        foreach ($this->getTransitions() as $transition) {
            if ($state == $transition->getSource()) {
                $transitions->push($transition);
            }
        }
        return $transitions;
    }

    /**
     * Workflow attribute name
     * @return string
     */
    public function getAttribute()
    {
        return self::ATTRIBUTE;
    }

    /**
     * Workflow initial state
     * @return string
     */
    protected function getInitialState()
    {
        return $this->getStates()->first();
    }
}