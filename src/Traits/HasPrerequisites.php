<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Support\Collection;

trait HasPrerequisites
{
    /**
     * Callable collection, that would be invoked before transit.
     */
    protected array $prerequisites = [];

    /**
     * Get registered preconditions.
     *
     * @return Collection<callable>
     */
    public function prerequisites(): Collection
    {
        return collect($this->prerequisites);
    }

    /**
     * Add prerequisite. Callback receives Model argument.
     */
    public function before(callable $prerequisite): self
    {
        $this->prerequisites[] = $prerequisite;

        return $this;
    }
}
