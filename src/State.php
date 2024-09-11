<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Contracts\StateEnum;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCallbacks;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasFootprint;
use Codewiser\Workflow\Traits\HasPrerequisites;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Codewiser\Workflow\Traits\HasValidationRules;
use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class State implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption, HasCallbacks, HasValidationRules, HasPrerequisites, HasFootprint;

    /**
     * @var \BackedEnum|string|int
     */
    public $value;

    /**
     * State new instance.
     *
     * @param \BackedEnum|string|int $value
     * @return static
     */
    public static function make($value): State
    {
        return new static($value);
    }

    /**
     * @param \BackedEnum|string|int $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        $this->context = new ContextRepository;
    }

    /**
     * Get caption of the State.
     */
    public function caption(): string
    {
        return $this->resolveCaption($this->engine()->model) ??
            ($this->value instanceof StateEnum
                ? $this->value->caption($this->engine()->model)
                : Value::name($this));
    }

    public function chronicle(?Model $performer): ?string
    {
        if (is_callable($this->footprint)) {
            return call_user_func($this->footprint, $this->engine()->model, $performer);
        }

        return null;
    }

    public function additional(): array
    {
        return $this->additional + ($this->value instanceof StateEnum ? $this->value->attributes() : []);
    }

    /**
     * Get proper ways out from the current state.
     *
     * @return TransitionCollection<Transition>
     */
    public function transitions(): TransitionCollection
    {
        return $this->engine()
            ->getTransitionListing()
            ->from($this->value)
            ->withoutForbidden();
    }

    /**
     * Get available transition to the given state.
     *
     * @param \BackedEnum|string|int $state
     */
    public function transitionTo($state): ?Transition
    {
        return $this
            ->transitions()
            ->to($state)
            ->first();
    }

    public function toArray(): array
    {
        return [
                'name' => $this->caption(),
                'value' => Value::scalar($this),
            ] + $this->additional();
    }

    /**
     * Check if state equals to current.
     *
     * @param \BackedEnum|string|int $state
     * @return bool
     */
    public function is($state): bool
    {
        return $this->value === $state;
    }

    /**
     * Check if state doesn't equal to current.
     *
     * @param \BackedEnum|string|int $state
     * @return bool
     */
    public function isNot($state): bool
    {
        return $this->value !== $state;
    }

    /**
     * Get or set (and validate) transition additional context.
     *
     * @throws ValidationException
     */
    public function context(array $context = null): ContextRepository
    {
        if (is_array($context)) {

            $rules = $this->validationRules();

            if ($rules) {
                $this->context = new ContextRepository(
                    validator($context, $rules)->validate()
                );
            }
        }

        return $this->context;
    }
}
