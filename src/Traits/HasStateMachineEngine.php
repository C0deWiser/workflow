<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\StateMachineEngine;

trait HasStateMachineEngine
{
    /**
     * @var StateMachineEngine|null
     */
    protected $engine = null;

    public function inject(StateMachineEngine $engine)
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * Method will fail if object was not injected before — it is ok.
     */
    public function engine(): StateMachineEngine
    {
        return $this->engine;
    }
}
