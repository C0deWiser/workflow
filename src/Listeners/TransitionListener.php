<?php

namespace Codewiser\Workflow\Listeners;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Models\TransitionHistory;
use Codewiser\Workflow\StateMachine;

class TransitionListener
{
    protected function newRecordFor(StateMachine $engine, Context $context): TransitionHistory
    {
        $log = new TransitionHistory();

        $log->blueprint = get_class($engine->blueprint);

        $log->performer()->associate($engine->getActor());
        $log->transitionable()->associate($engine->model);

        $log->source = $context->source()?->enum->value;
        $log->target = $context->target()->enum->value;
        $log->context = $context->data()->all() ?: null;

        $log->save();

        return $log;
    }

    public function handleModelInitialized(ModelInitialized $event): void
    {
        $this->newRecordFor($event->engine, $event->context);
    }

    public function handleModelTransited(ModelTransited $event): void
    {
        $this->newRecordFor($event->engine, $event->context);
    }
}
