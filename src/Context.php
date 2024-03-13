<?php

namespace Codewiser\Workflow;

use Illuminate\Config\Repository as ContextRepository;

class Context
{
    /**
     * @var Transition|State
     */
    protected $context;

    /**
     * @param  Transition|State  $context
     */
    public function __construct($context)
    {
        $this->context = $context;
    }

    /**
     * Source state. NULL means that model was just created.
     */
    public function source(): ?State
    {
        return $this->context instanceof Transition ? $this->context->source() : null;
    }

    /**
     * Target state.
     */
    public function target(): State
    {
        return $this->context instanceof Transition ? $this->context->target() : $this->context;
    }

    /**
     * Additional context.
     */
    public function data(): ContextRepository
    {
        return $this->context->context();
    }
}