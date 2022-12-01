<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Database\Eloquent\Model;

trait HasStateMachineEngine
{
    protected ?StateMachineEngine $engine = null;

    public function inject(StateMachineEngine $engine): static
    {
        $this->engine = $engine;

        return $this;
    }

    public function engine(): StateMachineEngine
    {
        return $this->engine;
    }
}
