<?php

namespace Codewiser\Workflow\Listeners;

use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Models\TransitionHistory;
use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Database\Eloquent\Model;

class TransitionListener
{
    protected function newRecordFor(Model $model, StateMachineEngine $engine): TransitionHistory
    {
        $log = new TransitionHistory;

        $log->blueprint = get_class($engine->blueprint);
        $log->performer()->associate($engine->getActor());
        $log->transitionable()->associate($model);

        return $log;
    }

    public function handleModelInitialized(ModelInitialized $event): void
    {
        $log = $this->newRecordFor($event->model, $event->engine);

        $log->target = $event->context->target()->scalar();

        $log->context = $event->context->data()->all() ?: null;

        $log->save();
    }

    public function handleModelTransited(ModelTransited $event): void
    {
        $log = $this->newRecordFor($event->model, $event->engine);

        $log->source = $event->context->source()->scalar();

        $log->target = $event->context->target()->scalar();

        $log->context = $event->context->data()->all() ?: null;

        $log->save();
    }
}
