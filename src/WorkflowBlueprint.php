<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Exceptions\WorkflowException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Workflow (aka State Machine) blueprint
 * @package Codewiser\Workflow
 */
abstract class WorkflowBlueprint
{
    public function __construct()
    {
        $this->validate();
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
     * Validates State Machine Blueprint
     * @throws WorkflowException
     */
    protected function validate()
    {
        $states = $this->getStates();
        $transitions = collect();
        foreach ($this->getTransitions() as $transition) {
            $s = $transition->getSource();
            $t = $transition->getTarget();

            if (!$states->contains($s)) {
                throw new WorkflowException("Buggy blueprint: transition from nowhere");
            }
            if (!$states->contains($t)) {
                throw new WorkflowException("Buggy blueprint: transition to nowhere");
            }
            if ($transitions->contains($s.$t)) {
                throw new WorkflowException("Buggy blueprint: transition duplicate");
            }
            $transitions->push($s.$t);
        }
    }

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