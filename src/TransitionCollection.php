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

    /**
     * @param  array  $items
     *
     * @return TransitionCollection
     */
    public static function make($items = []): TransitionCollection
    {
        $collection = [];

        foreach ($items as $item) {

            if (is_array($item)) {
                $item = Transition::make($item[0], $item[1]);
            }

            if ($item instanceof Transition) {
                // Filter unique transitions
                $key = Value::scalar($item->source).Value::scalar($item->target);

                if (!isset($collection[$key])) {
                    $collection[$key] = $item;
                }
            }
        }

        return new static(array_values($collection));
    }

    /**
     * Get transitions from given state.
     *
     * @param  mixed  $state
     */
    public function from($state): self
    {
        return $this->filter(fn(Transition $transition) => $transition->source === $state);
    }

    /**
     * Get transitions to given state.
     *
     * @param  mixed  $state
     */
    public function to($state): self
    {
        return $this->filter(fn(Transition $transition) => $transition->target === $state);
    }

    /**
     * Get transitions without fatal conditions.
     */
    public function withoutForbidden(): self
    {
        return $this
            ->reject(function (Transition $transition) {
                try {
                    $transition->validate();
                } catch (TransitionFatalException $exception) {
                    return true;
                } catch (TransitionRecoverableException $exception) {

                }
                return false;
            });
    }

    /**
     * Get authorized transitions.
     */
    public function onlyAuthorized(): self
    {
        return self::make(
            $this
                ->filter(fn(Transition $transition) => $transition->authorized())
                ->values()
        );
    }
}
