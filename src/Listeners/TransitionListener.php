<?php

namespace Codewiser\Workflow\Listeners;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Models\TransitionHistory;
use Codewiser\Workflow\Transition;
use Illuminate\Database\Eloquent\Model;

class TransitionListener
{
    protected function newRecordFor(Model $model, string $attribute, Context $context): TransitionHistory
    {
        $log = new (TransitionHistory::model())();

        $log->blueprint = $attribute;

        $log->performer()->associate(auth()->user());
        $log->transitionable()->associate($model);

        $log->source = $context->source()?->enum->value;
        $log->target = $context->target()->enum->value;

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
        $contextual = $context->transition() ?? $context->target();

        $data = $contextual->prepareForStoring($model, $context, $log);

        if ($contextual instanceof Transition) {
            $data = $contextual->target()->prepareForStoring($model, new Context($contextual, $data), $log);
        }

        return $this->filterStorable($data);
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
        $this->newRecordFor($event->engine->model, $event->engine->attribute, $event->context);
    }

    public function handleTransition(ModelTransited $event): void
    {
        $this->newRecordFor($event->engine->model, $event->engine->attribute, $event->context);
    }
}
