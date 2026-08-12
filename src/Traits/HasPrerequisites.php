<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasPrerequisites
{
    /**
     * Callable collection, that would be invoked before transit.
     *
     * @var array<int, callable(Model): void>
     */
    protected array $prerequisites = [];

    /**
     * Get registered preconditions.
     *
     * @return Collection<int, callable(Model): void>
     * @internal
     */
    public function prerequisites(): Collection
    {
        return collect($this->prerequisites);
    }

    /**
     * Callback will run before transition starts.
     * You may define few callbacks.
     *
     * @param  callable(Model): void  $prerequisite
     */
    public function before(callable $prerequisite): static
    {
        $this->prerequisites[] = $prerequisite;

        return $this;
    }
}
