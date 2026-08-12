<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\StateMachine;

trait HasEngine
{
    protected ?StateMachine $engine = null;

    public function inject(StateMachine $engine): static
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * The method will fail if an object was not injected before — it is ok.
     */
    public function engine(): StateMachine
    {
        return $this->engine;
    }
}
