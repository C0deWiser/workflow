<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\HasWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Illuminate\Support\Str;

class StateMachineEngine
{
    protected ?TransitionCollection $transitions = null;
    protected ?StateCollection $states = null;

    /**
     * Transition additional context.
     *
     * @var array
     */
    protected array $context = [];

    /**
     * @param WorkflowBlueprint $blueprint
     * @param Model $model
     */
    public function __construct(
        protected WorkflowBlueprint $blueprint,
        protected Model             $model)
    {
        //
    }

    /**
     * Get model's Workflow Blueprint.
     */
    public function blueprint(): WorkflowBlueprint
    {
        return $this->blueprint;
    }

    /**
     * Get all states of the workflow.
     *
     * @return StateCollection<State>
     */
    public function states(): StateCollection
    {
        if (!$this->states) {
            $this->states = StateCollection::make($this->blueprint->states())->injectWith($this);
        }

        return $this->states;
    }

    /**
     * Get all transitions in the workflow.
     *
     * @return TransitionCollection<Transition>
     */
    public function transitions(): TransitionCollection
    {
        if (!$this->transitions) {
            $this->transitions = TransitionCollection::make($this->blueprint->transitions())->injectWith($this);
        }

        return $this->transitions;
    }

    /**
     * Get workflow initial state.
     */
    public function initial(): State
    {
        return $this->states()->first();
    }

    /**
     * Get or set transition additional context.
     */
    public function context(array $context = null): array
    {
        if (is_array($context)) {
            $this->context = $context;
        }

        return $this->context;
    }

    /**
     * @return Model
     */
    public function model(): Model
    {
        return $this->model;
    }
}
