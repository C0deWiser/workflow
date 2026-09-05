<?php

namespace Codewiser\Workflow;

use Illuminate\Config\Repository as Userdata;

class Context
{
    public function __construct(protected Transition|State $contextual, protected array|Userdata $userdata = [])
    {
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
     */
    public function target(): State
    {
        return $this->transition()?->target() ?? $this->contextual;
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