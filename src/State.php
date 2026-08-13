<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Contracts\StateEnum;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasConditions;
use Codewiser\Workflow\Traits\HasContext;
use Codewiser\Workflow\Traits\HasDeadEnd;
use Codewiser\Workflow\Traits\HasEloquentEvents;
use Codewiser\Workflow\Traits\HasEngine;
use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @template TType of \BackedEnum
 */
class State implements Arrayable, Injectable
{
    use HasEngine, HasEloquentEvents, HasContext, HasDeadEnd, HasConditions;
    use HasAttributes {
        additional as protected selfAdditional;
    }
    use HasCaption {
        caption as protected selfCaption;
    }

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
        return $this->selfCaption()
            ?? (
            $this->enum instanceof StateEnum
                ? $this->enum->caption()
                : $this->enum->name
            );
    }

    public function additional(): array
    {
        return $this->selfAdditional()
            + (
            $this->enum instanceof StateEnum
                ? $this->enum->attributes()
                : []
            );
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

            ...$this->additional()
        ];
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
}
