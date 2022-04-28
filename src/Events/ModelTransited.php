<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\Transition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;

/**
 * State value was changed.
 */
class ModelTransited
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Model              $model,
        public StateMachineEngine $workflow,
        public Transition         $transition,
        public array              $context
    )
    {
    }
}
