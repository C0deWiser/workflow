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

            if ($item instanceof \BackedEnum) {
                $item = State::make($item);
            }

            $key = $item->enum->value;

            if (! isset($collection[$key])) {
                $collection[$key] = $item;
            }
        }

        return new static(array_values($collection), ...$args);
    }

    /**
     * Get initial state (could be overridden).
     */
    public function initial(\BackedEnum $enum = null): State
    {
        return $this->first(
            fn(State $state) => $state->enum === $enum
        ) ?? $this->first();
    }

    /**
     * Get the exact one state from a collection.
     *
     * @param  \BackedEnum  $enum
     *
     * @return State
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     */
    public function one(\BackedEnum $enum): State
    {
        return $this->sole(fn(State $st) => $st->is($enum));
    }
}
