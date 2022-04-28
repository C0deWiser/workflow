<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Model got initial state value.
 */
class ModelInitialized
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Model              $model,
        public StateMachineEngine $workflow
    )
    {
    }
}
