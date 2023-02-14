<?php

namespace Codewiser\Workflow;

class TransitionThreshold
{
    /**
     * @var null|callable
     */
    protected $condition = null;

    /**
     * @var null|callable
     */
    protected $callback = null;

    /**
     * @var null|callable
     */
    protected $allow = null;

    /**
     * @var null|callable
     */
    protected $history = null;

    /**
     * @param callable|null $condition Return TRUE if transition need more charge to reach threshold.
     * @param callable|null $allow Return TRUE if user was not charge the transition.
     * @param callable|null $callback Increase transition charge.
     */
    public function __construct(callable $condition, callable $allow, callable $callback, callable $history = null)
    {
        $this->condition = $condition;
        $this->allow = $allow;
        $this->callback = $callback;
        $this->history = $history;
    }

    public function history(Transition $transition): array
    {
        return $this->history ? (array)call_user_func($this->history, $transition) : [];
    }

    /**
     * Check if user allowed to charge the transition.
     */
    public function mayCharge(Transition $transition): bool
    {
        return (boolean)call_user_func($this->allow, $transition);
    }

    /**
     * Charge transition.
     */
    public function charge(Transition $transition): void
    {
        call_user_func($this->callback, $transition);
    }

    /**
     * Check if transition fully charged and ready to change state.
     */
    public function charged(Transition $transition): bool
    {
        return $this->charging($transition) >= 1;
    }

    /**
     * Check transition charging level (0-1).
     */
    public function charging(Transition $transition): float
    {
        return call_user_func($this->condition, $transition);
    }
}
