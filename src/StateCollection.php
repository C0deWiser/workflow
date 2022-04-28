<?php

namespace Codewiser\Workflow;

use Illuminate\Support\Collection;

class StateCollection extends Collection
{
    /**
     * Get the exact one state from collection.
     */
    public function one(State|string $state): State
    {
        return $this
            ->sole(function (State $st) use ($state) {
                return (string)$st == (string)$state;
            });
    }
}
