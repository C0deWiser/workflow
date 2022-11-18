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

class State implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption;

    /**
     * State new instance.
     */
    public static function make(BackedEnum|string|int $state): static
    {
        return new static($state);
    }

    public function __construct(public BackedEnum|string|int $state)
    {
        //
    }

    /**
     * Get caption of the State.
     */
    public function caption(): string
    {
        return $this->caption ?? ($this->state instanceof BackedEnum ? $this->state->name : $this->state);
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
            ->from($this->state)
            ->withoutForbidden();
    }

    /**
     * Get available transition to the given state.
     */
    public function transitionTo(BackedEnum|string|int $state): ?Transition
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
                'value' => $this->state instanceof BackedEnum ? $this->state->value : $this->state,
            ] + $this->additional();
    }

    /**
     * Check if state is equal to current.
     *
     * @param BackedEnum|string|int $state
     * @return bool
     */
    public function is(BackedEnum|string|int $state): bool
    {
        return $this->state === $state;
    }
}
