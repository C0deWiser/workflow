<?php

namespace Codewiser\Workflow;

use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Contracts\Auth\Access\Authorizable;

class Context
{
    public function __construct(protected Transition|State $contextual, protected ?Authorizable $actor = null)
    {
        //
    }

    /**
     * Get the transition (if it is).
     */
    public function transition(): ?Transition
    {
        return $this->contextual instanceof Transition ? $this->contextual : null;
    }

    /**
     * Source state. NULL means that model was just created.
     */
    public function source(): ?State
    {
        return $this->transition()?->source();
    }

    /**
     * Target state.
     */
    public function target(): State
    {
        return $this->transition()?->target() ?? $this->contextual;
    }

    /**
     * Get authenticated user for the context.
     */
    public function actor(): ?Authorizable
    {
        return $this->actor;
    }

    /**
     * Additional context.
     */
    public function data(): ContextRepository
    {
        return $this->contextual->context();
    }
}