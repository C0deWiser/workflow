<?php

namespace Codewiser\Workflow\Traits;

trait HasCallbacks
{
    /**
     * Callable collection, that would be invoked after event.
     */
    protected array $callbacks = [];

    /**
     * Get registered callbacks.
     */
    public function callbacks(): \Illuminate\Support\Collection
    {
        return collect($this->callbacks);
    }

    /**
     * Register callbacks that will be run after transition is done and the state is reached.
     *
     * @param callable(\Illuminate\Database\Eloquent\Model, \Codewiser\Workflow\Context): void $callback
     */
    public function after(callable $callback): static
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Run registered callbacks.
     */
    public function invoke(\Illuminate\Database\Eloquent\Model $model, \Codewiser\Workflow\Context $context): void
    {
        $this->callbacks()->each(
            fn(callable $callback) => call_user_func($callback, $model, $context)
        );
    }
}
