<?php

namespace Codewiser\Workflow;

class Charge
{
    /**
     * @var null|callable
     */
    protected $progress = null;

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
     * Every callback receives Model and Transition arguments.
     *
     * @param callable $progress Should return float (0÷1) with charge progress.
     * @param callable $callback Increase transition charge.
     */
    public static function make(callable $progress, callable $callback): Charge
    {
        return new static($progress, $callback);
    }

    /**
     * @param callable $progress Return float (0÷1) with charge progress.
     * @param callable $callback Increase transition charge.
     */
    public function __construct(callable $progress, callable $callback)
    {
        $this->progress = $progress;
        $this->callback = $callback;
    }

    /**
     * Add history callback. Callback should return an array.
     */
    public function withHistory(callable $callback): self
    {
        $this->history = $callback;

        return $this;
    }

    public function history(Transition $transition): array
    {
        return $this->history ? (array)call_user_func($this->history, $transition->engine()->model, $transition) : [];
    }

    /**
     * Callback should return FALSE if a user already charges the transition. It is TRUE if not defined.
     */
    public function allow(callable $callback): self
    {
        $this->allow = $callback;

        return $this;
    }

    /**
     * Check if user allowed charging the transition.
     */
    public function mayCharge(Transition $transition): bool
    {
        return is_null($this->allow) || call_user_func($this->allow, $transition->engine()->model, $transition);
    }

    /**
     * Charge transition.
     */
    public function charge(Transition $transition): void
    {
        call_user_func($this->callback, $transition->engine()->model, $transition);
    }

    /**
     * Check if transition fully charged and ready to change state.
     */
    public function charged(Transition $transition): bool
    {
        return $this->charging($transition) >= 1;
    }

    /**
     * Check transition charging level (0÷1).
     */
    public function charging(Transition $transition): float
    {
        return call_user_func($this->progress, $transition->engine()->model, $transition);
    }
}
