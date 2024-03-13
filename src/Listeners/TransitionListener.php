<?php

namespace Codewiser\Workflow\Listeners;

use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Models\TransitionHistory;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\Value;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TransitionListener
{
    protected function newRecordFor(Model $model, StateMachineEngine $engine): TransitionHistory
    {
        $log = new TransitionHistory;

        $log->transitionable()->associate($model);
        $log->blueprint = get_class($engine->blueprint);

        if (($user = auth()->user()) && ($user instanceof Model)) {
            $log->performer()->associate($user);
        }

        return $log;
    }

    public function handleModelInitialized(ModelInitialized $event): void
    {
        $log = $this->newRecordFor($event->model, $event->engine);

        $log->target = Value::scalar(
            $event->context->target()
        );

        $log->context = $event->context->data()->all() ?: null;

        $log->save();
    }

    public function handleModelTransited(ModelTransited $event): void
    {
        $log = $this->newRecordFor($event->model, $event->engine);

        $log->source = Value::scalar(
            $event->context->source()
        );

        $log->target = Value::scalar(
            $event->context->target()
        );

        $log->context = $event->context->data()->all() ?: null;

        $log->save();
    }
}
