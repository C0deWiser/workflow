<?php

namespace Codewiser\Workflow;

use Cassandra\Cluster\Builder;
use Codewiser\Journalism\Journal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Codewiser\Workflow\Exceptions\WorkflowException;

abstract class WorkflowBlueprint
{
    /**
     * @var Model
     */
    protected $model;

    private $transition_comment;

    /**
     * Attribute name. It keeps workflow state
     */
    const ATTRIBUTE = 'workflow';

    /**
     * @return string|null
     */
    public function getTransitionComment()
    {
        return $this->transition_comment;
    }

    /**
     * @param string $comment
     */
    public function setTransitionComment($comment)
    {
        $this->transition_comment = $comment;
    }

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
        $state = $this->model->getAttribute($this->getAttributeName());
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
    public function getAttributeName()
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

    /**
     * Workflow history
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function journal()
    {
        return Journal::get($this->model, 'transited');
    }
}