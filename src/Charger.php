<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Traits\HasEngine;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * Charger for a transition.
 */
class Charger implements Injectable
{
    use HasEngine;

    /**
     * Callback to get current charge level.
     *
     * @var callable(Model, Context): float
     */
    protected $progress;

    /**
     * Callback to increase charge.
     *
     * @var callable(Model, Context): void
     */
    protected $callback;

    /**
     * Callback to check if charging allowed.
     *
     * @var null|callable
     */
    protected $allow = null;

    /**
     * Callback to get charging history.
     *
     * @var null|callable
     */
    protected $history = null;

    /**
     * Every callback receives Model and Transition arguments.
     *
     * @param  callable(Model, Context): float  $progress  Callback to get current charge level (0÷1).
     * @param  callable(Model, Context): void  $callback  Callback to increase charge.
     */
    public static function make(callable $progress, callable $callback): Charger
    {
        return new static($progress, $callback);
    }

    /**
     * @param  callable(Model, Context): float  $progress  Callback to get current charge level (0÷1).
     * @param  callable(Model, Context): void  $callback  Callback to increase charge.
     */
    public function __construct(callable $progress, callable $callback)
    {
        $this->progress = $progress;
        $this->callback = $callback;
    }

    /**
     * Callback should return FALSE if a user is not allowed to charge the transition.
     * It is TRUE if not defined.
     *
     * @param  callable(Model, Context): bool  $callback
     */
    public function allow(callable $callback): static
    {
        $this->allow = $callback;

        return $this;
    }

    /**
     * Add history callback.
     * Callback should return an Arrayable, containing the history of charging.
     *
     * @param  callable(Model, Context): Arrayable  $callback
     */
    public function withHistory(callable $callback): static
    {
        $this->history = $callback;

        return $this;
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
    public function charge(Transition $transition, array $userdata): void
    {
        // Userdata here not validated
        // Just filter it with rules keys

        $userdata = array_filter($userdata,
            fn(string $key) => in_array($key, array_keys($transition->validationRules())),
            ARRAY_FILTER_USE_KEY
        );

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
     *
     * @internal
     */
    public function charging(Transition $transition): float
    {
        return call_user_func_array($this->progress, $this->func_args($transition));
    }

    protected function func_args(Transition $transition, array $userdata = []): array
    {
        return [$this->engine->model, new Context($transition, $userdata)];
    }
}
