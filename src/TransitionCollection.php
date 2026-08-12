<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\Injection;
use Illuminate\Support\Collection;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Illuminate\Support\Facades\Gate;

/**
 * @extends Collection<string, Transition>
 */
class TransitionCollection extends Collection
{
    use Injection;

    public static function make($items = [], ...$args): static
    {
        $collection = [];

        foreach ($items as $item) {

            if (is_array($item)) {
                $item = Transition::make($item[0], $item[1]);
            }

            if ($item instanceof Transition) {
                // Filter unique transitions
                $key = $item->source->value.$item->target->value;

                if (!isset($collection[$key])) {
                    $collection[$key] = $item;
                }
            }
        }

        return new static(array_values($collection), ...$args);
    }

    /**
     * Get transitions from a given state.
     */
    public function from(\BackedEnum $enum): static
    {
        return $this->filter(
            fn(Transition $transition) => $transition->source === $enum
        );
    }

    /**
     * Get transitions to a given state.
     */
    public function to(\BackedEnum $enum): static
    {
        return $this->filter(
            fn(Transition $transition) => $transition->target === $enum
        );
    }

    /**
     * Get transitions without fatal conditions.
     */
    public function withoutForbidden(): static
    {
        return $this
            ->reject(function (Transition $transition) {
                try {
                    $transition->validate();
                } catch (TransitionFatalException) {
                    return true;
                } catch (TransitionRecoverableException) {
                    return false;
                }
                return false;
            });
    }

    /**
     * Get authorized transitions.
     */
    public function onlyAuthorized(): static
    {
        $filtered = $this
            ->filter(fn(Transition $transition) => $transition->authorized())
            ->values();

        return new static($filtered);
    }
}
