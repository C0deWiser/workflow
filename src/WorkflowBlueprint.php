<?php

namespace Codewiser\Workflow;

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
     * @return array<string,State>
     * @example [new, review, published, correcting]
     */
    abstract protected function states(): array;

    /**
     * Array of allowed transitions between states.
     *
     * @return array<array,Transition>
     * @example [[new, review], [review, published], [review, correcting], [correcting, review]]
     */
    abstract protected function transitions(): array;

    /**
     * Validates State Machine Blueprint.
     *
     * @throws ItemNotFoundException|MultipleItemsFoundException
     */
    protected function validate(): void
    {
        $states = $this->getStates();
        $transitions = collect();

        $blueprint = class_basename($this);

        $this->getTransitions()
            ->each(function (Transition $transition) use ($states, $transitions, $blueprint) {
                $s = $transition->source();
                $t = $transition->target();

                if (!$states->contains($s)) {
                    throw new ItemNotFoundException("Invalid {$blueprint}: transition from nowhere: {$s}");
                }
                if (!$states->contains($t)) {
                    throw new ItemNotFoundException("Invalid {$blueprint}: transition to nowhere: {$t}");
                }
                if ($transitions->contains($s . $t)) {
                    throw new MultipleItemsFoundException("Invalid {$blueprint}: transition duplicate {$s}-{$t}");
                }
                $transitions->push($s . $t);
            });
    }

    /**
     * Array of states.
     *
     * @return StateCollection<State>
     */
    public function getStates(): StateCollection
    {
        $states = new StateCollection();

        foreach ($this->states() as $state) {
            if (is_string($state)) {
                $state = State::define($state);
            }
            $states->add($state);
        }

        return $states;
    }

    /**
     * Array of transitions between states.
     *
     * @return TransitionCollection<Transition>
     */
    public function getTransitions(): TransitionCollection
    {
        $transitions = new TransitionCollection();

        foreach ($this->transitions() as $transition) {
            if (is_array($transition)) {
                $transition = Transition::define($transition[0], $transition[1]);
            }
            $transitions->add($transition);
        }

        return $transitions;
    }
}
