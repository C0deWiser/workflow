<?php

namespace Codewiser\Workflow;

use BackedEnum;
use Codewiser\Workflow\Traits\Injection;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;

class StateCollection extends Collection
{
    use Injection;

    public static function make($items = []): static
    {
        $collection = new static();

        foreach ($items as $item) {

            if (State::enum($item) || is_scalar($item)) {
                $item = State::make($item);
            }

            if ($item instanceof State) {
                $collection->add($item);
            }

        }

        return $collection;
    }

    /**
     * Get the exact one state from collection.
     *
     * @param State|BackedEnum|string|int $state
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     */
    public function one(mixed $state): State
    {
        return $this->sole(function (State $st) use ($state) {
            return State::scalar($st) === State::scalar($state);
        });
    }
}
