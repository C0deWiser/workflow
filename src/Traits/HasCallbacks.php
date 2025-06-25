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
     * Callable collection, that would be invoked after event.
     */
    protected array $callbacks = [];

    /**
     * Get registered transition callbacks.
     *
     * @return Collection<callable>
     */
    public function callbacks(): Collection
    {
        return collect($this->callbacks);
    }

    /**
     * Callback(s) will run after transition is done or state is reached.
     * Callback receives Model and optional Transition arguments.
     */
    public function after(callable $callback): self
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Run callbacks.
     *
     * @return void
     */
    public function invoke(Model $model, Context $context)
    {
        $this->callbacks()
            ->each(function (callable $callback) use ($model, $context) {
                call_user_func($callback, $model, $context);
            });
    }
}
