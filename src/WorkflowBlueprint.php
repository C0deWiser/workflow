<?php

namespace Codewiser\Workflow;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;

/**
 * Workflow blueprint.
 *
 * @template TType of \BackedEnum
 */
abstract class WorkflowBlueprint
{
    /**
     * @return callable(): (null|Authorizable)
     */
    public function actor(): callable
    {
        return fn() => auth()->user();
    }

    /**
     * Authorization for running transitions.
     *
     * - null — No transition authorization.
     * - string — Action name to call policy.
     * - callable — Custom authorization; may return bool or Response, or throw AuthorizationException.
     *
     * @return null|string|callable(Model, Context): (bool|Response)
     */
    public function authorization(): null|string|callable
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
