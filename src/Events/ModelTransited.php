<?php

namespace Codewiser\Workflow\Events;

use Illuminate\Database\Eloquent\Model;

class ModelTransited
{
    use \Illuminate\Queue\SerializesModels;

    public $model;
    public $workflow;
    public $state;
    public $comment;

    /**
     * Create a new event instance.
     *
     * @param Model $model
     * @param string $workflow
     * @param string $state
     * @param string|null $comment
     */
    public function __construct(Model $model, $workflow, $state, $comment = null)
    {
        $this->model = $model;
        $this->workflow = $workflow;
        $this->state = $state;
        $this->comment = $comment;
    }
}