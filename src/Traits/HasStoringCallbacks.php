<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Context;
use Illuminate\Database\Eloquent\Model;

trait HasStoringCallbacks
{
    /**
     * Callable collection, that would be invoked when the context is persisted
     * into the transition history.
     */
    protected array $onStoringCallbacks = [];

    /**
     * Callback will run right before the context is stored into
     * `transition_history`. It may mutate the context, e.g. store uploaded
     * files and replace them with the paths.
     * You may define few callbacks.
     *
     * @param  callable(Model, Context): void  $callback
     */
    public function storing(callable $callback): static
    {
        $this->onStoringCallbacks[] = $callback;

        return $this;
    }

    /**
     * Run storing callbacks, mutating the given context.
     *
     * @internal
     */
    public function store(Model $model, Context $context): void
    {
        foreach ($this->onStoringCallbacks as $callback) {
            call_user_func($callback, $model, $context);
        }
    }
}