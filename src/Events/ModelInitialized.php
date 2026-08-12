<?php

namespace Codewiser\Workflow\Events;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachine;
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

    public function __construct(public StateMachine $engine, public Context $context)
    {
        //
    }
}
