<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\State;
use Codewiser\Workflow\Transition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasCallbacks
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
     * @param  callable(Model, Context): void|bool  $callback
     *
     * @return $this
     */
    public function saving(callable $callback): self
    {
        $this->onSavingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Callback will run after transition is done and state is reached.
     * You may define few callbacks.
     *
     * @param  callable(Model, Context): void  $callback
     *
     * @return $this
     */
    public function after(callable $callback): self
    {
        $this->onSavedCallbacks[] = $callback;

        return $this;
    }

    /**
     * Run callbacks.
     *
     * @return void|bool
     */
    public function invoke(Model $model, Context $context)
    {
        if ($model->isDirty() || !$model->exists) {
            foreach ($this->onSavingCallbacks as $callback) {
                if (call_user_func($callback, $model, $context) === false) {
                    return false;
                }
            }
        } else {
            foreach ($this->onSavedCallbacks as $callback) {
                call_user_func($callback, $model, $context);
            }
        }
    }
}
