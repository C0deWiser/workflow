<?php

namespace Codewiser\Workflow\Listeners;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Events\TransitionCharged;
use Codewiser\Workflow\Models\TransitionHistory;
use Codewiser\Workflow\StateMachine;
use Illuminate\Database\Eloquent\Model;

class TransitionListener
{
    protected function newRecordFor(StateMachine $engine, Context $context): TransitionHistory
    {
        $model = $engine->model;
        $log = new (TransitionHistory::model())();

        $log->blueprint = $engine->attribute;

        $log->performer()->associate(auth()->user());
        $log->transitionable()->associate($model);

        $log->source = $context->source()?->enum->value;

        // A chargeable transition may have been redirected, so the model landed in
        // another state than the one its transition declares as its target.
        $log->target = $context->landedIn()->enum->value;

        // Store safe userdata.
        $userdata = $this->filterStorable($context->data()->all()) ?: null;
        $log->context = $userdata;

        $log->save();

        // Call user callback to prepare context for storing.
        $updated = $this->invokeStorableCallbacks($model, $context, $log);

        // Update the record only if the context was changed.
        if (($updated = $updated ?: null) != $userdata) {
            $log->context = $updated;
            $log->save();
        }

        return $log;
    }

    protected function invokeStorableCallbacks(Model $model, Context $context, TransitionHistory $log): array
    {
        $state = $context->landedIn();

        // The model was only initialized in a state.
        if (! $transition = $context->transition()) {
            return $this->filterStorable($state->prepareForStoring($model, $context, $log));
        }

        $data = $transition->prepareForStoring($model, $context, $log);

        // State callbacks, of the state the model landed in.
        $contextual = new Context($transition, $data, $context->redirectedTo());

        return $this->filterStorable($state->prepareForStoring($model, $contextual, $log));
    }

    protected function filterStorable(array $data): array
    {
        foreach ($data as $key => $value) {

            if (is_object($value)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->filterStorable($value);
            }
        }

        return $data;
    }

    public function handleInitialization(ModelInitialized $event): void
    {
        $this->newRecordFor($event->engine, $event->context);
    }

    public function handleTransition(ModelTransited $event): void
    {
        $this->newRecordFor($event->engine, $event->context);
    }

    public function handleCharged(TransitionCharged $event): void
    {
        $this->newRecordFor($event->engine, $event->context);
    }
}
