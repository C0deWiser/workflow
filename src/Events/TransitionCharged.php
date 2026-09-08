<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\StateMachine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A transition was charged, but not yet completed.
 */
class TransitionCharged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public StateMachine $engine, public Context $context)
    {
        //
    }
}