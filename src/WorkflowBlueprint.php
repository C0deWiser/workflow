<?php

namespace Codewiser\Workflow;


/**
 * Workflow blueprint.
 */
abstract class WorkflowBlueprint
{
    /**
     * Array of available Model Workflow steps. First one is initial.
     *
     * @return array<int,int|string|\BackedEnum|\Codewiser\Workflow\State>
     * @example [new, review, published, correcting]
     */
    abstract public function states(): array;

    /**
     * Array of allowed transitions between states.
     *
     * @return array<int,array<int,int|string|\BackedEnum|\Codewiser\Workflow\State>|\Codewiser\Workflow\Transition>
     * @example [[new, review], [review, published], [review, correcting], [correcting, review]]
     */
    abstract public function transitions(): array;
}
