<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Illuminate\Database\Eloquent\Model;

/**
 * State or transition may be forbidden by some conditions.
 */
trait HasDeadEnd
{
    protected array $deadEnds = [
        'when'   => [],
        'unless' => []
    ];

    /**
     * Hide state/transition if condition is false.
     *
     * @param  callable(Model, Context): (void|bool)  $callback  May throw TransitionFatalException instead of returning false.
     */
    public function when(callable $callback): static
    {
        $this->deadEnds['when'][] = $callback;

        return $this;
    }

    /**
     * Hide state/transition if condition is true.
     *
     * @param  callable(Model, Context): (void|bool)  $callback  May throw TransitionFatalException instead of returning true.
     */
    public function unless(callable $callback): static
    {
        $this->deadEnds['unless'][] = $callback;

        return $this;
    }

    /**
     * Hide a state/transition?
     *
     * @internal
     */
    public function isForbidden(): bool
    {
        $context = new Context($this);

        foreach ($this->deadEnds['when'] as $when) {
            try {
                if (false === call_user_func($when, $this->engine()->model, $context)) {
                    return true;
                }
            } catch (TransitionFatalException) {
                return true;
            }
        }

        foreach ($this->deadEnds['unless'] as $unless) {
            try {
                if (true === call_user_func($unless, $this->engine()->model, $context)) {
                    return true;
                }
            } catch (TransitionFatalException) {
                return true;
            }
        }

        return false;
    }
}