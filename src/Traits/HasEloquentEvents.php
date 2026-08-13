<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Context;
use Illuminate\Database\Eloquent\Model;

trait HasEloquentEvents
{
    /**
     * Callable collection, that would be invoked during event.
     */
    protected array $onSavingCallbacks = [];

    /**
     * Callable collection, that would be invoked after event.
     */
    protected array $onSavedCallbacks = [];

    /**
     * Callback will run inside a transition before model is saved.
     * You may define few callbacks.
     *
     * @param  callable(Model, Context): (void|bool)  $callback
     */
    public function saving(callable $callback): static
    {
        $this->onSavingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Callback will run after transition is done and state is reached.
     * You may define few callbacks.
     *
     * @param  callable(Model, Context): void  $callback
     */
    public function saved(callable $callback): static
    {
        $this->onSavedCallbacks[] = $callback;

        return $this;
    }

    /**
     * Run callbacks.
     *
     * @return void|bool
     * @internal
     */
    public function invoke(Model $model, Context $context, string $event)
    {
        if ($event === 'saving') {
            foreach ($this->onSavingCallbacks as $callback) {
                if (call_user_func($callback, $model, $context) === false) {
                    return false;
                }
            }
        } elseif ($event === 'saved') {
            foreach ($this->onSavedCallbacks as $callback) {
                call_user_func($callback, $model, $context);
            }
        }
    }
}
