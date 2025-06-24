<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\Injection;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;

/**
 * @extends \Illuminate\Support\Collection<array-key, Transition>
 */
class TransitionCollection extends \Illuminate\Support\Collection
{
    use Injection;

    /**
     * @param  array  $items
     *
     * @return TransitionCollection
     */
    public static function make($items = []): TransitionCollection
    {
        $collection = [];

        $scalar = fn(\UnitEnum $enum) => $enum instanceof \BackedEnum ? $enum->value : $enum->name;

        foreach ($items as $item) {

            if (is_array($item)) {
                $item = Transition::make($item[0], $item[1]);
            }

            if ($item instanceof Transition) {
                // Filter unique transitions
                $key = $scalar($item->source).$scalar($item->target);

                if (!isset($collection[$key])) {
                    $collection[$key] = $item;
                }
            }
        }

        return new static(array_values($collection));
    }

    /**
     * Get transitions from given state.
     */
    public function from(\UnitEnum $state): static
    {
        return $this->filter(fn(Transition $transition) => $transition->source === $state);
    }

    /**
     * Get transitions to given state.
     */
    public function to(\UnitEnum $state): static
    {
        return $this->filter(fn(Transition $transition) => $transition->target === $state);
    }

    /**
     * Get transitions without fatal conditions.
     */
    public function withoutForbidden(): static
    {
        return $this->reject(function (Transition $transition) {
            try {
                $transition->validate();
                return false;
            } catch (TransitionFatalException $e) {
                //dump($e->getMessage(), $transition->caption());
                return true;
            } catch (TransitionRecoverableException $e) {
                //dump($e->getMessage(), $transition->caption());
                return false;
            }
        });
    }

    /**
     * Get authorized transitions.
     */
    public function onlyAuthorized(): static
    {
        return $this
            ->filter(fn(Transition $transition) => $transition->authorized())
            ->values();
    }
}
