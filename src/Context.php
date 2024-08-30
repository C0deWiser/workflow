<?php

namespace Codewiser\Workflow;

use Illuminate\Config\Repository as ContextRepository;

class Context
{
    /**
     * @var Transition|State
     */
    protected $contextual;

    /**
     * @param  Transition|State  $contextual
     */
    public function __construct($contextual)
    {
        $this->contextual = $contextual;
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
     * Additional context.
     */
    public function data(): ContextRepository
    {
        return $this->contextual->context();
    }
}