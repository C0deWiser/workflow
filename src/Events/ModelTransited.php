<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\StateMachine;
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

    public function __construct(public StateMachine $engine, public Context $context)
    {
        //
    }
}
