<?php

namespace Codewiser\Workflow\Listeners;

use BackedEnum;
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
        $log->blueprint = $engine->getBlueprint()::class;

        if (($user = auth()->user()) && ($user instanceof Model)) {
            $log->performer()->associate($user);
        }

        return $log;
    }

    public function handleModelInitialized(ModelInitialized $event): void
    {
        $log = $this->newRecordFor($event->engine->getModel(), $event->engine);

        $state = $event->engine->states()->initial()->state;

        $log->target = $state instanceof BackedEnum ? $state->value : $state;

        $log->save();
    }

    public function handleModelTransited(ModelTransited $event): void
    {
        $log = $this->newRecordFor($event->engine->getModel(), $event->engine);

        $source = $event->transition->source();
        $target = $event->transition->target();

        $log->source = $source instanceof BackedEnum ? $source->value : $source;
        $log->target = $target instanceof BackedEnum ? $target->value : $target;

        try {
            if ($context = $event->transition->context()) {
                $log->context = $context;
            }
        } catch (ValidationException) {
            // Actually it was successfully validated...
        }

        $log->save();
    }
}
