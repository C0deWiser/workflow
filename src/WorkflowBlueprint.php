<?php

namespace Codewiser\Workflow;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;

/**
 * Workflow blueprint.
 */
abstract class WorkflowBlueprint implements CastsAttributes
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
        $states = $this->getStates()
            ->map(function (State $state) {
                return $state->value;
            });
        $transitions = collect();

        $blueprint = class_basename($this);

        $this->getTransitions()
            ->each(function (Transition $transition) use ($states, $transitions, $blueprint) {
                $s = $transition->source;
                $t = $transition->target;

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
            if (is_scalar($state)) {
                $state = State::make($state);
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
                $transition = Transition::make($transition[0], $transition[1]);
            }
            $transitions->add($transition);
        }

        return $transitions;
    }

    protected static array $engines = [];

    /**
     * Transform the attribute from the underlying model values.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        $key = $model::class . ':' . $key;

        if (!isset(self::$engines[$key])) {
            self::$engines[$key] = new StateMachineEngine($this, $model);
        }

        return $value ? $this->getStates()->one($value)
            ->inject(self::$engines[$key]) :
            $value;
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if (is_scalar($value)) {
            return $value;
        }
        if ($value instanceof State) {
            return $value->value;
        }

        return $value;
    }
}
