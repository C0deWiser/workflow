<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\Transition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelInitialized
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Model $model;
    public StateMachineEngine $workflow;

    /**
     * Create a new event instance.
     *
     * @param Model $model
     * @param StateMachineEngine $workflow
     * @param Transition $transition
     */
    public function __construct(Model $model, StateMachineEngine $workflow)
    {
        $this->model = $model;
        $this->workflow = $workflow;
    }
}