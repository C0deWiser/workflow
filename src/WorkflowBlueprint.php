<?php

namespace Codewiser\Workflow;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Workflow blueprint.
 *
 * @template TType of \BackedEnum
 */
abstract class WorkflowBlueprint
{
    /**
     * Default authorization for running transitions.
     *
     * @return null|callable(Model, Context): (bool|Response) May throw AuthorizationException.
     */
    public function authorization(): ?callable
    {
        return null;
    }

    /**
     * Array of available Model Workflow steps. The first one is initial.
     *
     * @return array<int,TType|State>
     */
    abstract public function states(): array;

    /**
     * Array of allowed transitions between states.
     *
     * @return array<int,array<int,TType|State>|Transition>
     */
    abstract public function transitions(): array;
}
