<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasEloquentEvents;
use Codewiser\Workflow\Traits\HasPrerequisites;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Codewiser\Workflow\Traits\HasValidationRules;
use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\ValidationException;

/**
 * @template TType of \BackedEnum
 */
class State implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption, HasEloquentEvents, HasValidationRules, HasPrerequisites;

    /**
     * State new instance.
     *
     * @param  TType  $enum
     */
    public static function make(\BackedEnum $enum): static
    {
        return new static($enum);
    }

    /**
     * @param  TType  $enum
     */
    public function __construct(public \BackedEnum $enum)
    {
        $this->context = new ContextRepository;
    }

    public function __serialize(): array
    {
        return [
            'enum'    => $this->enum,
            'engine'  => serialize($this->engine),
            'context' => serialize($this->context),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->enum = $data['enum'];
        $this->engine = unserialize($data['engine']);
        $this->context = unserialize($data['context']);
    }

    /**
     * Get the caption of the State.
     */
    public function caption(): string
    {
        return $this->resolveCaption($this->engine()->model) ?? $this->enum->name;
    }

    public function additional(): array
    {
        return $this->resolveAttributes($this->engine()->model);
    }

    /**
     * Get proper ways out from the current state.
     *
     * @return TransitionCollection<int, Transition>
     */
    public function transitions(): TransitionCollection
    {
        return $this->engine()
            ->getTransitionListing()
            ->from($this->enum)
            ->withoutForbidden();
    }

    /**
     * Get available transition to the given state.
     *
     * @param  TType  $enum
     */
    public function transitionTo(\BackedEnum $enum): ?Transition
    {
        return $this
            ->transitions()
            ->to($enum)
            ->first();
    }

    public function toArray(): array
    {
        return [
                'name'  => $this->caption(),
                'value' => $this->enum->value,
            ] + $this->additional();
    }

    /**
     * Check if state equals to current.
     *
     * @param  TType  $enum
     */
    public function is(\BackedEnum $enum): bool
    {
        return $this->enum === $enum;
    }

    /**
     * Check if the state doesn't equal to current.
     *
     * @param  TType  $enum
     */
    public function isNot(\BackedEnum $enum): bool
    {
        return $this->enum !== $enum;
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
