<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Model got initial state value.
 */
class ModelInitialized
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Model $model;

    public function __construct(public StateMachineEngine $engine, public Context $context)
    {
        $this->model = $this->engine->model;
    }
}
