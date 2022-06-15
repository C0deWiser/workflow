<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachineEngine;

trait HasStateMachineEngine
{
    protected ?StateMachineEngine $engine = null;

    /**
     * Vivify transition with StateMachineEngine.
     */
    public function inject(StateMachineEngine $engine): static
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * Get State Machine Engine.
     */
    public function engine(): StateMachineEngine
    {
        return $this->engine;
    }
}
