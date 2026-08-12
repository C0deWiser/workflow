<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * State or transition may have some conditions to run.
 */
trait HasConditions
{
    protected array $conditions = [];

    /**
     * State/transition may run if meet given condition.
     *
     * @param  callable(Model): (null|string)  $callback Should return string to describe condition to a user.
     */
    public function condition(callable $callback): static
    {
        $this->conditions[] = $callback;

        return $this;
    }

    /**
     * Get a list of problems with a state/transition.
     *
     * @return array<int, string>
     */
    public function issues(): array
    {
        return collect($this->conditions)
            ->map(fn(callable $callback) => call_user_func($callback, $this->engine()->model))
            ->filter()
            ->values()
            ->toArray();
    }
}