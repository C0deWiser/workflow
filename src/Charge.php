<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Traits\HasEngine;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class Charge implements Injectable
{
    use HasEngine;

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
     * @param  callable(Model, Context): float  $progress  Should return float (0÷1) with charge progress.
     * @param  callable(Model, Context): void  $callback  Increase transition charge.
     */
    public static function make(callable $progress, callable $callback): Charge
    {
        return new static($progress, $callback);
    }

    /**
     * @param  callable(Model, Context): float  $progress  Return float (0÷1) with charge progress.
     * @param  callable(Model, Context): void  $callback  Increase transition charge.
     */
    public function __construct(callable $progress, callable $callback)
    {
        $this->progress = $progress;
        $this->callback = $callback;
    }

    /**
     * Add history callback. Callback should return an Arrayable, containing the history of charging.
     *
     * @param  callable(Model, Context): Arrayable  $callback
     */
    public function withHistory(callable $callback): static
    {
        $this->history = $callback;

        return $this;
    }

    /**
     * Callback should return FALSE if a user already charges the transition. It is TRUE if not defined.
     *
     * @param  callable(Model, Context): bool  $callback
     */
    public function allow(callable $callback): static
    {
        $this->allow = $callback;

        return $this;
    }

    protected function func_args(Transition $transition, array $userdata = []): array
    {
        return [$this->engine->model, new Context($transition, $userdata)];
    }

    /**
     * Get provided history.
     *
     * @internal
     */
    public function history(Transition $transition): array
    {
        return is_callable($this->history)
            ? (array) call_user_func_array($this->history, $this->func_args($transition))
            : [];
    }

    /**
     * Check if user allowed charging the transition.
     *
     * @internal
     */
    public function mayCharge(Transition $transition): bool
    {
        return is_null($this->allow) || call_user_func_array($this->allow, $this->func_args($transition));
    }

    /**
     * Charge transition.
     *
     * @internal
     */
    public function charge(Transition $transition, array $userdata = []): void
    {
        call_user_func_array($this->callback, $this->func_args($transition, $userdata));
    }

    /**
     * Check if transition fully charged and ready to change state.
     *
     * @internal
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
        return call_user_func_array($this->progress, $this->func_args($transition));
    }
}
