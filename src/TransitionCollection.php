<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\Injection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

                if (! isset($collection[$key])) {
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
        $filtered = $this
            ->filter(fn(Transition $transition) => $transition->source === $enum)
            ->values();

        return new static($filtered);
    }

    /**
     * Get transitions to a given state.
     */
    public function to(\BackedEnum $enum): static
    {
        $filtered = $this
            ->filter(fn(Transition $transition) => $transition->target === $enum)
            ->values();

        return new static($filtered);
    }

    /**
     * Get transitions without forbidden.
     */
    public function withoutForbidden(): static
    {
        $filtered = $this
            ->reject(fn(Transition $transition) => $transition->isForbidden())
            ->values();

        return new static($filtered);
    }

    /**
     * Get authorized transitions.
     */
    public function authorized(): static
    {
        $filtered = $this
            ->filter(function (Transition $transition) {
                try {
                    $transition->authorize();
                    return true;
                } catch (AuthorizationException) {
                    return false;
                }
            })
            ->values();

        return new static($filtered);
    }
}
