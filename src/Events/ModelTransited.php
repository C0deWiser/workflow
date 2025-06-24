<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * State value was changed.
 */
class ModelTransited
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Model $model;

    public function __construct(public StateMachineEngine $engine, public Context $context)
    {
        $this->model = $this->engine->model;
    }
}
