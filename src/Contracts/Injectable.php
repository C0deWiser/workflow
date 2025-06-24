<?php

namespace Codewiser\Workflow\Contracts;

use Codewiser\Workflow\StateMachineEngine;

interface Injectable
{
    /**
     * Vivify object with StateMachineEngine.
     */
    public function inject(StateMachineEngine $engine): static;

    /**
     * Get State Machine Engine.
     */
    public function engine(): StateMachineEngine;
}
