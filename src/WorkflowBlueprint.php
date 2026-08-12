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
    public function actor(): \Closure
    {
        return fn() => auth()->user();
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

    /**
     * Authorisation for running transitions.
     *
     * - null — No transition authorisation.
     * - string — Action name to call policy.
     * - callable — Custom authorisation; may return bool or Response, or throw AuthorizationException.
     *
     * @return null|string|callable(Model, Transition): (bool|Response)
     */
    abstract public function authorization(): null|string|callable;
}
