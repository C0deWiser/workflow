<?php

namespace Codewiser\Workflow\Events;

use Illuminate\Database\Eloquent\Model;

class ModelTransited
{
    use \Illuminate\Queue\SerializesModels;

    public $model;
    public $workflow;
    public $source;
    public $target;
    public $payload;

    /**
     * Create a new event instance.
     *
     * @param Model $model
     * @param string $workflow
     * @param $source
     * @param $target
     * @param array $payload
     */
    public function __construct(Model $model, $workflow, $source, $target, $payload = [])
    {
        $this->model = $model;
        $this->workflow = $workflow;
        $this->source = $source;
        $this->target = $target;
        $this->payload = $payload;
    }
}