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

    /**
     * Get grouped states.
     *
     * @param string $group
     * @return $this
     */
    public function grouped(string $group): self
    {
        return $this
            ->filter(function (State $state) use ($group) {
                return $state->group() == $group;
            });
    }
}