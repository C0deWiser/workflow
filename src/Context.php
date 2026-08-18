<?php

namespace Codewiser\Workflow;

use Illuminate\Config\Repository as Userdata;

class Context
{
    public function __construct(protected Transition|State $contextual, protected array $userdata = [])
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
     * Additional context.
     */
    public function data(): Userdata
    {
        return new Userdata($this->userdata);
    }
}