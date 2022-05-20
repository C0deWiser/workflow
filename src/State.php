<?php

namespace Codewiser\Workflow;

use BackedEnum;
use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Illuminate\Support\Str;

class State implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption;

    /**
     * State new instance.
     *
     * @param BackedEnum|string|int $state
     */
    public static function make(mixed $state): static
    {
        return new static($state);
    }

    /**
     * @param BackedEnum|string|int $value
     */
    public function __construct(public mixed $value)
    {
        //
    }

    /**
     * Get caption of the State.
     */
    public function caption(): string
    {
        $fallback = Str::snake(class_basename($this->engine->blueprint())) .
            ".states." . self::scalar($this->value);

        return $this->caption ?? $fallback;

    }

    /**
     * Reset workflow to the initial state.
     */
    public function reset(): void
    {
        $this->value = $this->engine->initial();
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
     *
     * @param State|BackedEnum|string|int $state
     */
    public function transitionTo(mixed $state): ?Transition
    {
        return $this
            ->transitions()
            ->to($state)
            ->first();
    }

    /**
     * Authorize transition to the new state.
     *
     * @param State|BackedEnum|string|int $target
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     * @throws AuthorizationException
     */
    public function authorize(mixed $target): static
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
                'name' => $this->caption(),
                'value' => $this->value,
            ] + $this->additional();
    }

    /**
     * Check if state is equal to current.
     *
     * @param State|BackedEnum|string|int $state
     * @return bool
     */
    public function is(mixed $state): bool
    {
        return self::scalar($this) === self::scalar($state);
    }

    /**
     * Get scalar value.
     *
     * @param State|BackedEnum|string|int $value
     * @return string|int
     */
    public static function scalar(mixed $value): mixed
    {
        if ($value instanceof State) {
            $value = $value->value;
        }

        if (self::enum($value)) {
            $value = @$value->value ?? $value->name;
        }

        return $value;
    }

    /**
     * Check if value is enumerable.
     *
     * @param mixed $value
     * @return bool
     */
    public static function enum(mixed $value): bool
    {
        return is_object($value) && function_exists('enum_exists') && enum_exists($value::class);
    }
}
