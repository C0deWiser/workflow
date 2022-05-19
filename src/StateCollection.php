<?php

namespace Codewiser\Workflow;

use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;

class StateCollection extends Collection
{
    /**
     * Get the exact one state from collection.
     *
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     */
    public function one(State|string|int $state): State
    {
        return $this
            ->sole(function (State $st) use ($state) {
                return $st->is($state);
            });
    }
}
