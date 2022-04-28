<?php

namespace Codewiser\Workflow;

use BackedEnum;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;

/**
 * Workflow blueprint.
 */
abstract class WorkflowBlueprint
{
    public function __construct()
    {
        $this->validate();
    }

    /**
     * Array of available Model Workflow steps. First one is initial.
     *
     * @return array<BackedEnum>
     * @example [new, review, published, correcting]
     */
    abstract public function states(): array;

    /**
     * Array of allowed transitions between states.
     *
     * @return array<Transition>
     * @example [[new, review], [review, published], [review, correcting], [correcting, review]]
     */
    abstract public function transitions(): array;

    /**
     * Validates State Machine Blueprint.
     *
     * @throws ItemNotFoundException|MultipleItemsFoundException
     */
    protected function validate(): void
    {
        $states = collect($this->states());
        $transitions = collect();

        $blueprint = class_basename($this);

        collect($this->transitions())
            ->each(function (Transition $transition) use ($states, $transitions, $blueprint) {
                $s = $transition->source();
                $t = $transition->target();

                if (!$states->contains($s)) {
                    throw new ItemNotFoundException("Invalid {$blueprint}: transition from nowhere: {$s->name}");
                }
                if (!$states->contains($t)) {
                    throw new ItemNotFoundException("Invalid {$blueprint}: transition to nowhere: {$t->name}");
                }
                if ($transitions->contains($s->name . $t->name)) {
                    throw new MultipleItemsFoundException("Invalid {$blueprint}: transition duplicate {$s->name}-{$t->name}");
                }
                $transitions->push($s->name . $t->name);
            });
    }
}
