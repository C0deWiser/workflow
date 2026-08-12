<?php

namespace Codewiser\Workflow\Contracts;

use Codewiser\Workflow\StateMachine;

interface Injectable
{
    /**
     * Vivify object with StateMachineEngine.
     *
     * @return $this
     */
    public function inject(StateMachine $engine);

    /**
     * Get State Machine Engine.
     */
    public function engine(): StateMachine;
}
