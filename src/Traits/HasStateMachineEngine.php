<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\StateMachineEngine;

trait HasStateMachineEngine
{
    protected ?StateMachineEngine $engine = null;

    public function inject(StateMachineEngine $engine)
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * The method will fail if an object was not injected before — it is ok.
     */
    public function engine(): StateMachineEngine
    {
        return $this->engine;
    }
}
