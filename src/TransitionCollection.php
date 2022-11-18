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
                // Filter unique transitions
                $key = ($item->source instanceof BackedEnum ? $item->source->value : $item->source)
                    . ($item->target instanceof BackedEnum ? $item->target->value : $item->target);
                if (!isset($collection[$key])) {
                    $collection[$key] = $item;
                }
            }
        }

        return new static(array_values($collection));
    }

    /**
     * Transitions from given state.
     */
    public function from(BackedEnum|string|int $state): static
    {
        return $this->filter(function (Transition $transition) use ($state) {
            return $transition->source === $state;
        });
    }

    /**
     * Transitions to given state.
     */
    public function to(BackedEnum|string|int $state): static
    {
        return $this->filter(function (Transition $transition) use ($state) {
            return $transition->target === $state;
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
    public function onlyAuthorized(): static
    {
        return $this->filter(function (Transition $transition) {
            if ($ability = $transition->authorization()) {
                if (is_string($ability)) {
                    return Gate::allows($ability, $transition->engine()->getModel());
                } elseif (is_callable($ability)) {
                    return call_user_func($ability, $transition->engine()->getModel());
                }
            }
            return true;
        });
    }
}
