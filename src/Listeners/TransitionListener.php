<?php

namespace Codewiser\Workflow\Listeners;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Models\TransitionHistory;
use Illuminate\Database\Eloquent\Model;

class TransitionListener
{
    protected function newRecordFor(Model $model, string $attribute, Context $context): TransitionHistory
    {
        $log = new TransitionHistory();

        $log->blueprint = $attribute;

        $log->performer()->associate(auth()->user());
        $log->transitionable()->associate($model);

        $log->source = $context->source()?->enum->value;
        $log->target = $context->target()->enum->value;
        $log->context = $context->storable() ?: null;

        $log->save();

        return $log;
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
