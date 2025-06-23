<?php

namespace Codewiser\Workflow;


/**
 * Workflow blueprint.
 *
 * @template TType
 */
abstract class WorkflowBlueprint
{
    public function userResolver(): \Closure
    {
        return fn() => auth()->user();
    }

    /**
     * Array of available Model Workflow steps. The first one is initial.
     *
     * @return array<int,TType|\Codewiser\Workflow\State>
     * @example [new, review, published, correcting]
     */
    abstract public function states(): array;

    /**
     * Array of allowed transitions between states.
     *
     * @return array<int,array<int,TType|\Codewiser\Workflow\State>|\Codewiser\Workflow\Transition>
     * @example [[new, review], [review, published], [review, correcting], [correcting, review]]
     */
    abstract public function transitions(): array;
}
