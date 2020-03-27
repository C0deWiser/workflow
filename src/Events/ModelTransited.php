<?php

namespace Codewiser\Workflow\Events;

use Illuminate\Database\Eloquent\Model;

class ModelTransited
{
    use \Illuminate\Queue\SerializesModels;

    public $model;
    public $workflow;
    public $state;

    /**
     * Create a new event instance.
     *
     * @param Model $model
     * @param string $workflow
     * @param string $state
     * @return void
     */
    public function __construct(Model $model, $workflow, $state)
    {
        $this->model = $model;
        $this->workflow = $workflow;
        $this->state = $state;
    }
}