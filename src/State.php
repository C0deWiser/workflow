<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Illuminate\Support\Str;

class State implements Arrayable
{
    use HasAttributes, HasStateMachineEngine;

    protected ?string $caption = null;

    /**
     * State new instance.
     */
    public static function make(string|int $state): static
    {
        return new static($state);
    }

    public function __construct(public string|int $value)
    {
        //
    }

    /**
     * Set State caption.
     */
    public function as(string $caption): static
    {
        if ($caption)
            $this->caption = $caption;

        return $this;
    }

    /**
     * Get caption of the State.
     */
    public function caption(): string
    {
        $fallback = Str::snake(class_basename($this->engine->blueprint())) . ".states.{$this->value}";

        return $this->caption ?? $fallback;

    }

    /**
     * Reset workflow to the initial state.
     */
    public function reset(): void
    {
        $this->value = $this->engine->initial()->value;
    }

    /**
     * Get proper ways out from the current state.
     *
     * @return TransitionCollection<Transition>
     */
    public function transitions(): TransitionCollection
    {
        return $this->engine
            ->transitions()
            ->from($this->value)
            ->withoutForbidden();
    }

    /**
     * Get available transition to the given state.
     */
    public function transitionTo(State|string|int $state): ?Transition
    {
        return $this
            ->transitions()
            ->to($state)
            ->first();
    }

    /**
     * Authorize transition to the new state.
     *
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     * @throws AuthorizationException
     */
    public function authorize(string $target): static
    {
        $transition = $this->transitions()
            ->to($target)
            ->sole();

        if ($ability = $transition->authorization()) {
            if (is_string($ability)) {
                Gate::authorize($ability, $this->engine->model());
            } elseif (is_callable($ability)) {
                if (!call_user_func($ability, $this->engine->model())) {
                    throw new AuthorizationException();
                }
            }
        }

        return $this;
    }

    /**
     * Set additional context before transition.
     */
    public function context(array $context): static
    {
        $this->engine->context($context);

        return $this;
    }

    public function toArray(): array
    {
        return [
                'value' => $this->value,
                'caption' => $this->caption()
            ] + $this->additional();
    }

    public function is(State|string|int $state): bool
    {
        return $this->value === ($state instanceof State ? $state->value : $state);
    }
}
