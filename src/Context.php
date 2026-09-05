<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Models\TransitionHistory;
use Illuminate\Config\Repository as Userdata;
use Illuminate\Database\Eloquent\Model;

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
     * Get context data, that is safe to persist (e.g. into transition_history).
     * Uploaded files (and any other objects) are filtered out recursively.
     * Any given data is passed through the same filter.
     *
     * @return array<int|string, mixed>
     */
    public function storable(): array
    {
        return $this->filterObjects($this->userdata->all());
    }

    /**
     * Run storing callbacks of the contextual transition (and its target state)
     * or of a state, returning the context as an array, prepared to persist
     * into the transition history.
     *
     * The record is persisted by the caller, so callbacks may reference
     * it as the owner of the files they store.
     *
     * @return array<int|string, mixed>
     *
     * @internal
     */
    public function prepareForStoring(Model $model, TransitionHistory $log): array
    {
        $data = $this->contextual->prepareForStoring($this->userdata->all(), $model, $log);

        if ($transition = $this->transition()) {
            $data = $transition->target()->prepareForStoring($data, $model, $log);
        }

        return $this->filterObjects($data);
    }

    protected function filterObjects(array $data): array
    {
        foreach ($data as $key => $value) {

            if (is_object($value)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->filterObjects($value);
            }
        }

        return $data;
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