<?php

namespace Codewiser\Workflow;


/**
 * Workflow blueprint.
 *
 * @template TType of \BackedEnum
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
     */
    abstract public function states(): array;

    /**
     * Array of allowed transitions between states.
     *
     * @return array<int,array<int,TType|\Codewiser\Workflow\State>|\Codewiser\Workflow\Transition>
     */
    abstract public function transitions(): array;
}
