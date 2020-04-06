<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Exceptions\StateMachineConsistencyException;
use Codewiser\Workflow\Exceptions\InvalidTransitionException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Workflow (aka State Machine) blueprint
 * @package Codewiser\Workflow
 */
abstract class WorkflowBlueprint
{
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
     * Array of states
     * @return string[]|Collection
     */
    public function getStates()
    {
        return collect($this->states());
    }

    /**
     * Array of transitions between states
     * @return Transition[]|Collection
     */
    public function getTransitions()
    {
        return collect($this->transitions());
    }
}