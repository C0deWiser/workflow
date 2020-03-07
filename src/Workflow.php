<?php

namespace Codewiser\Workflow;

use Illuminate\Database\Eloquent\Model;
use Codewiser\Workflow\Exceptions\WorkflowException;

abstract class Workflow
{
    /**
     * @var Model
     */
    protected $model;
    /**
     * Attribute name. It keeps workflow state
     * @var string
     */
    protected $attribute = 'workflow';
    /**
     * Array of available Model Workflow steps. First one is initial
     * @var array
     * @example [new, review, published, correcting]
     */
    protected $states = [];
    /**
     * Array of allowed transitions between states
     * @var array
     * @example [review => [new, review], publish => [review, published], amend => [review, correcting], review => [correcting, review]]
     */
    protected $transitions = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function creating(Model $model)
    {
        // Force initial state of Model
        $model->setAttribute($this->getAttribute(), $this->getInitialState());
    }

    /**
     * @param Model $model
     * @throws WorkflowException
     */
    public function updating(Model $model)
    {
        if ($model->isDirty($this->getAttribute())) {

            $source = $model->getOriginal($this->getAttribute());
            $target = $model->getAttribute($this->getAttribute());

            if ($transition = $this->findTransition($source, $target)) {
                $this->checkPrecondition($transition);
            } else {
                throw new WorkflowException('Model can not be transited to given state');
            }
        }
    }

    /**
     * Checks if transition can be performed.
     *
     * Example: If article has empty body it can't be published.
     *
     * @param string $transition
     * @throws WorkflowException
     */
    abstract public function checkPrecondition($transition);

    /**
     * Find transition from source to target state
     * @param string $source
     * @param string $target
     * @return string|null
     */
    protected function findTransition($source, $target)
    {
        foreach ($this->transitions as $transition => $states) {
            if ($states[0] == $source && $states[1] == $target) {
                return $transition;
            }
        }
    }

    /**
     * Possible transitions from current state
     * @return array
     */
    public function possibleTransitions()
    {
        $state = $this->model->getAttribute($this->getAttribute());
        $transitions = [];
        foreach ($this->transitions as $transition => $states) {
            if ($state == $states[0]) {
                $transitions[] = $transition;
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
        return $this->attribute;
    }

    /**
     * Workflow initial state
     * @return string
     */
    protected function getInitialState()
    {
        return $this->states[0];
    }
}