<?php

namespace Codewiser\Workflow\Traits;

trait HasPrerequisites
{
    /**
     * Callable collection, that would be invoked before transit.
     */
    protected array $prerequisites = [];

    /**
     * Get registered preconditions.
     */
    public function prerequisites(): \Illuminate\Support\Collection
    {
        return new \Illuminate\Support\Collection($this->prerequisites);
    }

    /**
     * Add prerequisite. Callback receives Model argument.
     *
     * @param callable(\Illuminate\Database\Eloquent\Model): void $prerequisite
     */
    public function before(callable $prerequisite): static
    {
        $this->prerequisites[] = $prerequisite;

        return $this;
    }
}
