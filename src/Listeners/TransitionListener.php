<?php

namespace Codewiser\Workflow\Listeners;

use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Models\TransitionHistory;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TransitionListener
{
    protected function newRecordFor(Model $model, StateMachineEngine $engine): TransitionHistory
    {
        $log = new TransitionHistory;

        $log->transitionable()->associate($model);
        $log->blueprint = $engine->blueprint()::class;

        if (($user = auth()->user()) && ($user instanceof Model)) {
            $log->performer()->associate($user);
        }

        return $log;
    }

    public function handleModelInitialized(ModelInitialized $event): void
    {
        $log = $this->newRecordFor($event->model, $event->engine);

        $log->target = State::scalar($event->engine->initial());

        $log->save();
    }

    public function handleModelTransited(ModelTransited $event): void
    {
        $log = $this->newRecordFor($event->model, $event->engine);

        $log->source = State::scalar($event->transition->source());
        $log->target = State::scalar($event->transition->target());

        try {
            if ($context = $event->transition->context()) {
                $log->context = $context;
            }
        } catch (ValidationException $e) {
            // Actually it was successfully validated...
        }

        $log->save();
    }
}
