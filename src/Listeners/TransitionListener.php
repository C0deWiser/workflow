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
        $log = $this->newRecordFor($event->engine->model, $event->engine);

        $log->target = Value::scalar(
            $event->engine->state()
        );

        $log->save();
    }

    public function handleModelTransited(ModelTransited $event): void
    {
        $log = $this->newRecordFor($event->engine->model, $event->engine);

        $log->source = Value::scalar(
            $event->transition->source()
        );

        $log->target = Value::scalar(
            $event->transition->target()
        );

        try {
            if ($context = $event->transition->context()) {
                $log->context = $context;
            }
        } catch (ValidationException $exception) {
            // Actually it was successfully validated...
        }

        $log->save();
    }
}
