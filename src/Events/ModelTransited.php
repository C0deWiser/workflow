<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\Transition;
use Illuminate\Database\Eloquent\Model;

class ModelTransited
{
    use \Illuminate\Queue\SerializesModels;

    public $model;
    public $workflow;
    public $transition;

    /**
     * Create a new event instance.
     *
     * @param Model $model
     * @param string $workflow
     * @param $source
     * @param $target
     * @param array $payload
     */
    public function __construct(Model $model, $workflow, Transition $transition)
    {
        $this->model = $model;
        $this->workflow = $workflow;
        $this->transition = $transition;
    }
}