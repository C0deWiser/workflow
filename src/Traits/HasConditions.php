<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
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
     * @param  callable(Model, Context): (void|string)  $callback  Should either return string with description or throw TransitionRecoverableException.
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
     *
     * @internal
     */
    public function issues(): array
    {
        return collect($this->conditions)
            ->map(function (callable $callback) {
                try {
                    return call_user_func($callback, $this->engine()->model, new Context($this));
                } catch (TransitionRecoverableException $e) {
                    return $e->getMessage();
                }
            })
            ->filter()
            ->values()
            ->toArray();
    }
}