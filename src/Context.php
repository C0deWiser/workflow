<?php

namespace Codewiser\Workflow;

use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Contracts\Auth\Authenticatable;

class Context
{
    public function __construct(protected Transition|State $contextual, protected ?Authenticatable $actor = null)
    {
        //
    }

    /**
     * Source state. NULL means that model was just created.
     */
    public function source(): ?State
    {
        return $this->contextual instanceof Transition ? $this->contextual->source() : null;
    }

    /**
     * Target state.
     */
    public function target(): State
    {
        return $this->contextual instanceof Transition ? $this->contextual->target() : $this->contextual;
    }

    /**
     * Get authenticated user for the context.
     */
    public function actor(): ?Authenticatable
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