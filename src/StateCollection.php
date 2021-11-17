<?php

namespace Codewiser\Workflow;

class StateCollection extends \Illuminate\Support\Collection
{
    /**
     * Get the exact one state from collection.
     *
     * @param string|State $state
     * @return State
     */
    public function one($state): State
    {
        return $this
            ->sole(function (State $st) use ($state) {
                return (string)$st == (string)$state;
            });
    }
}