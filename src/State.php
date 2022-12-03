<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Illuminate\Contracts\Support\Arrayable;

class State implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption;

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
    }

    /**
     * Get caption of the State.
     */
    public function caption(): string
    {
        return $this->caption ?? Value::name($this);
    }

    /**
     * Get proper ways out from the current state.
     *
     * @return TransitionCollection<Transition>
     */
    public function transitions(): TransitionCollection
    {
        return $this->engine
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
     * Check if state is equal to current.
     *
     * @param \BackedEnum|string|int $state
     * @return bool
     */
    public function is($state): bool
    {
        return $this->value === $state;
    }
}
