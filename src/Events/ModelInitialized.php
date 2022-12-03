<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Model got initial state value.
 */
class ModelInitialized
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var StateMachineEngine
     */
    public $engine;

    public function __construct(StateMachineEngine $engine)
    {
        $this->engine = $engine;
    }
}
