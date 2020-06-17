<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\Transition;
use Illuminate\Database\Eloquent\Model;

class ModelTransited
{
    use \Illuminate\Queue\SerializesModels;

    /**
     * @var Model
     */
    public $model;
    /**
     * @var StateMachineEngine
     */
    public $workflow;
    /**
     * @var Transition
     */
    public $transition;
    /**
     * @var array
     */
    public $payload;

    /**
     * Create a new event instance.
     *
     * @param Model $model
     * @param StateMachineEngine $workflow
     * @param $transition
     * @param array $payload
     */
    public function __construct(Model $model, StateMachineEngine $workflow, Transition $transition, $payload = [])
    {
        $this->model = $model;
        $this->workflow = $workflow;
        $this->transition = $transition;
        $this->payload = $payload;
    }
}