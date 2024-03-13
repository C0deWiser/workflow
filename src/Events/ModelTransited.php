<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\Transition;
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

    /**
     * @var StateMachineEngine
     */
    public $engine;

    /**
     * @var Model
     */
    public $model;

    /**
     * @var Context
     */
    public $context;

    public function __construct(StateMachineEngine $engine, Context $context)
    {
        $this->engine = $engine;
        $this->model = $engine->model;
        $this->context = $context;
    }
}
