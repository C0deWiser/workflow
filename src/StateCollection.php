<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\Injection;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;

/**
 * @extends \Illuminate\Support\Collection<array-key, State>
 */
class StateCollection extends \Illuminate\Support\Collection
{
    use Injection;

    public static function make($items = []): static
    {
        $collection = new static();

        foreach ($items as $item) {

            if (!($item instanceof State)) {
                $item = State::make($item);
            }

            $collection->add($item);
        }

        return $collection;
    }

    public function initial(): State
    {
        return $this->first();
    }

    /**
     * Get the exact one state from a collection.
     *
     * @param  \UnitEnum  $state
     *
     * @return State
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     */
    public function one(\UnitEnum $state): State
    {
        return $this->sole(fn(State $st) => $st->is($state));
    }
}
