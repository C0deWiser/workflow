<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\Transition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Database\Eloquent\Model;

class ModelTransited
{
    use Dispatchable, InteractsWithSockets, \Illuminate\Queue\SerializesModels;

    /**
     * @var Model
     */
    public Model $model;
    /**
     * @var StateMachineEngine
     */
    public StateMachineEngine $workflow;
    /**
     * @var Transition
     */
    public Transition $transition;

    /**
     * Create a new event instance.
     *
     * @param Model $model
     * @param StateMachineEngine $workflow
     * @param $transition
     */
    public function __construct(Model $model, StateMachineEngine $workflow, Transition $transition)
    {
        $this->model = $model;
        $this->workflow = $workflow;
        $this->transition = $transition;
    }
}