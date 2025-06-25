<?php

namespace Codewiser\Workflow;

use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Contracts\Auth\Authenticatable;

class Context
{
    /**
     * @var Transition|State
     */
    protected $contextual;

    protected ?Authenticatable $actor;

    /**
     * @param  Transition|State  $contextual
     * @param  null|Authenticatable  $actor
     */
    public function __construct($contextual, ?Authenticatable $actor = null)
    {
        $this->contextual = $contextual;
        $this->actor = $actor;
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