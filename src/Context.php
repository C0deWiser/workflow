<?php

namespace Codewiser\Workflow;

use Illuminate\Config\Repository as Userdata;

class Context
{
    public function __construct(
        protected Transition|State $contextual,
        protected array|Userdata $userdata = [],
        protected ?State $redirectedTo = null
    ) {
        if (is_array($this->userdata)) {
            $this->userdata = new Userdata($this->userdata);
        }
    }

    /**
     * Get the transition (if it is).
     */
    public function transition(): ?Transition
    {
        return $this->contextual instanceof Transition ? $this->contextual : null;
    }

    /**
     * Source state. NULL means that model was just created.
     */
    public function source(): ?State
    {
        return $this->transition()?->source();
    }

    /**
     * Target state.
     *
     * For a redirected chargeable transition this is still the state the
     * transition declares, not the state the model landed in. Use
     * `landedIn()` to get the latter.
     */
    public function target(): State
    {
        return $this->transition()?->target() ?? $this->contextual;
    }

    /**
     * State a chargeable transition was redirected to.
     *
     * NULL unless this context belongs to a live, redirected transition.
     * A context rebuilt from the transition history has none: it stores
     * the state that was landed in, and `target()` returns it.
     */
    public function redirectedTo(): ?State
    {
        return $this->redirectedTo;
    }

    /**
     * State the model lands in.
     *
     * State-level callbacks belong here: a redirected transition lands in its
     * redirected state, so that state's callbacks are the ones to run.
     */
    public function landedIn(): State
    {
        return $this->redirectedTo ?? $this->target();
    }

    /**
     * Additional context.
     */
    public function data(): Userdata
    {
        return $this->userdata;
    }

    /**
     * Get data and rules for validating user context.
     *
     * Returns arguments for
     * `validator(array $data, array $rules, array $messages, array $attributes)`,
     * so it may be used as variadic: `validator(...$context->validation())`.
     *
     * @return array{0: array<int|string, mixed>, 1: array<array-key, string>, 2: array<array-key, string>, 3: array<array-key, string>}
     */
    public function validation(): array
    {
        $v = $this->contextual->validation() ?? new Validation([]);

        return [
            $this->userdata->all(),
            $v->rules,
            $v->messages,
            $v->attributes
        ];
    }
}