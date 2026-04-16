<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\Injection;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;

/**
 * @extends Collection<int, State>
 */
class StateCollection extends Collection
{
    use Injection;

    public static function make($items = [], ...$args): self
    {
        $collection = [];

        foreach ($items as $item) {

            if (!($item instanceof State)) {
                $item = State::make($item);
            }

            $key = Value::scalar($item->value);

            if (!isset($collection[$key])) {
                $collection[$key] = $item;
            }
        }

        return new static(array_values($collection), ...$args);
    }

    public function initial(): State
    {
        return $this->first();
    }

    /**
     * Get the exact one state from a collection.
     *
     * @param  mixed  $state
     *
     * @return State
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     */
    public function one($state): State
    {
        return $this->sole(fn(State $st) => $st->is($state));
    }
}
