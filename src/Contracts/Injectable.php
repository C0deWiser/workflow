<?php

namespace Codewiser\Workflow\Contracts;

use Codewiser\Workflow\StateMachineEngine;

interface Injectable
{
    /**
     * Vivify object with StateMachineEngine.
     *
     * @return $this
     */
    public function inject(StateMachineEngine $engine);

    /**
     * Get State Machine Engine.
     */
    public function engine(): StateMachineEngine;
}
