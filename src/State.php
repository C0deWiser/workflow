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

/**
 * @template TType of \UnitEnum
 */
class State implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption, HasCallbacks, HasValidationRules, HasPrerequisites, HasFootprint;

    /**
     * State new instance.
     *
     * @param  TType&\UnitEnum  $value
     *
     * @return static
     */
    public static function make(\UnitEnum $value): State
    {
        return new static($value);
    }

    /**
     * @param  TType&\UnitEnum  $value
     */
    public function __construct(public \UnitEnum $value)
    {
        $this->context = new ContextRepository;
    }

    public function __serialize(): array
    {
        return [
            'value'   => $this->value,
            'engine'  => serialize($this->engine),
            'context' => serialize($this->context),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->value = $data['value'];
        $this->engine = unserialize($data['engine']);
        $this->context = unserialize($data['context']);
    }

    /**
     * Get the State scalar value.
     */
    public function scalar(): int|string
    {
        return $this->value instanceof \BackedEnum
            ? $this->value->value
            : $this->value->name;
    }

    /**
     * Get the caption of the State.
     */
    public function caption(): string
    {
        return $this->resolveCaption($this->engine()->model) ??
            ($this->value instanceof StateEnum
                ? $this->value->caption()
                : $this->value->name
            );
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
     * @param  TType&\UnitEnum  $state
     */
    public function transitionTo(\UnitEnum $state): ?Transition
    {
        return $this
            ->transitions()
            ->to($state)
            ->first();
    }

    public function toArray(): array
    {
        return [
                'name'  => $this->caption(),
                'value' => $this->scalar(),
            ] + $this->additional();
    }

    /**
     * Check if state equals to current.
     *
     * @param  TType&\UnitEnum  $state
     *
     * @return bool
     */
    public function is(\UnitEnum $state): bool
    {
        return $this->value === $state;
    }

    /**
     * Check if the state doesn't equal to current.
     *
     * @param  TType&\UnitEnum  $state
     *
     * @return bool
     */
    public function isNot(\UnitEnum $state): bool
    {
        return $this->value !== $state;
    }

    /**
     * Get or set (and validate) transition additional context.
     *
     * @throws ValidationException
     */
    public function withContext(array $context): static
    {
        $rules = $this->validationRules();

        if ($rules) {
            $this->context = new ContextRepository(
                validator($context, $rules)->validate()
            );
        }

        return $this;
    }
}
