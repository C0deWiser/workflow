<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Models\TransitionHistory;
use Closure;
use Illuminate\Database\Eloquent\Model;

trait HasStoringCallbacks
{
    /**
     * Callback, that would be invoked when the context is persisted
     * into the transition history.
     *
     * @var null|callable
     */
    protected $onStoringCallbacks = null;

    /**
     * Callback will run right before the context is stored into
     * `transition_history`. The record is already persisted by that time,
     * so the callback may reference it as the owner of stored files.
     * Callback receives the current context and returns a new context as
     * an array (e.g. store uploaded files and replace them with the paths).
     * Returning NULL keeps the context unchanged.
     *
     * @param  callable(Model, Context, TransitionHistory): ?array  $callback
     */
    public function storing(callable $callback): static
    {
        $this->onStoringCallbacks = $callback;

        return $this;
    }

    /**
     * Run the storing callback. Returns the resulting context as an array.
     * The callback may return a new context array to replace the previous one.
     *
     * @internal
     */
    public function prepareForStoring(array $data, Model $model, TransitionHistory $log): array
    {
        if (is_callable($this->onStoringCallbacks)) {
            return call_user_func($this->onStoringCallbacks, $model, new Context($this, $data), $log) ?? $data;
        }

        return $data;
    }
}