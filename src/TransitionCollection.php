<?php

namespace Codewiser\Workflow;

use BackedEnum;
use Codewiser\Workflow\Traits\Injection;
use Illuminate\Support\Collection;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Illuminate\Support\Facades\Gate;

/**
 * @method Transition first(callable $callback = null, $default = null)
 * @method Transition sole($key = null, $operator = null, $value = null)
 */
class TransitionCollection extends Collection
{
    use Injection;

    public static function make($items = []): static
    {
        $collection = [];

        foreach ($items as $item) {

            if (is_array($item)) {
                $item = Transition::make($item[0], $item[1]);
            }

            if ($item instanceof Transition) {
                // It may be multiple definitions in one...
                foreach (Transition::decompose($item) as $decomposed) {
                    // Filter unique transitions
                    $collection[State::scalar($decomposed->source).State::scalar($decomposed->target)] = $decomposed;
                }
            }
        }

        return new static(array_values($collection));
    }
    /**
     * Transitions from given state.
     *
     * @param State|BackedEnum|string|int $state
     * @return $this
     */
    public function from(mixed $state): static
    {
        return $this->filter(function (Transition $transition) use ($state) {
            return State::scalar($transition->source) === State::scalar($state);
        });
    }

    /**
     * Transitions to given state.
     *
     * @param State|BackedEnum|string|int $state
     * @return $this
     */
    public function to(mixed $state): static
    {
        return $this->filter(function (Transition $transition) use ($state) {
            return State::scalar($transition->target) === State::scalar($state);
        });
    }

    /**
     * Transitions without fatal conditions.
     */
    public function withoutForbidden(): static
    {
        return $this->reject(function (Transition $transition) {
            try {
                $transition->validate();
            } catch (TransitionFatalException) {
                return true;
            } catch (TransitionRecoverableException) {

            }
            return false;
        });
    }

    /**
     * Authorized transitions.
     */
    public function authorized(): static
    {
        return $this->filter(function (Transition $transition) {
            if ($ability = $transition->authorization()) {
                if (is_string($ability)) {
                    return Gate::allows($ability, $transition->engine()->model());
                } elseif (is_callable($ability)) {
                    return call_user_func($ability, $transition->engine()->model());
                }
            }
            return true;
        });
    }
}
