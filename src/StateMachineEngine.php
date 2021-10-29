<?php


namespace Codewiser\Workflow;


use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Exceptions\StateMachineConsistencyException;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Exceptions\TransitionPayloadException;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StateMachineEngine
{
    /**
     * @var Model|Workflow
     */
    protected Model $model;

    /**
     * Attribute name. It keeps workflow state.
     *
     * @var string
     */
    protected string $attribute;

    /**
     * @var WorkflowBlueprint
     */
    protected WorkflowBlueprint $blueprint;

    public function __construct(WorkflowBlueprint $blueprint, Model $model, string $attribute)
    {
        $this->model = $model;
        $this->blueprint = $blueprint;
        $this->attribute = $attribute;
    }

    public function __toString()
    {
        return $this->getState();
    }

    /**
     * Get State Machine Blueprint.
     *
     * @return WorkflowBlueprint
     */
    public function getBlueprint(): WorkflowBlueprint
    {
        return $this->blueprint;
    }

    /**
     * Get human readable [current or any] state caption.
     *
     * @param null|string $state
     * @return array|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public function getStateCaption($state = null)
    {
        $state = $state ?: $this->getState();
        return trans(Str::snake(class_basename($this->blueprint)) . ".states.{$state}");
    }

    /**
     * Array of the model available workflow states.
     *
     * @return Collection|string[]
     */
    public function getStates(): Collection
    {
        return $this->blueprint->getStates();
    }

    /**
     * Array of allowed transitions between states.
     *
     * @return TransitionCollection
     */
    public function getTransitions(): TransitionCollection
    {
        return $this->blueprint->getTransitions()
            ->each(function (Transition $transition) {
                $transition->inject($this->model, $this->attribute);
            });
    }

    /**
     * Possible (for current user) transitions from the current state.
     *
     * @return TransitionCollection
     */
    public function getRelevantTransitions(): TransitionCollection
    {
        return $this->getTransitions()
            ->goingFrom($this->getState())
            ->valid();
    }

    /**
     * Workflow attribute name.
     *
     * @return string
     */
    public function getAttributeName(): string
    {
        return $this->attribute;
    }

    /**
     * Workflow initial state.
     *
     * @return string
     */
    public function getInitialState(): string
    {
        return $this->getStates()->first();
    }

    /**
     * Model current state.
     *
     * @return string|null
     */
    public function getState(): ?string
    {
        return $this->model->getAttribute($this->getAttributeName());
    }

    /**
     * Authorize transition to the new state.
     * 
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Support\ItemNotFoundException
     * @throws \Illuminate\Support\MultipleItemsFoundException
     * @return Model
     */
    public function authorize(string $target): Model
    {
        $transition = $this->getRelevantTransitions()
            ->goingTo($target)
            ->sole();

        if ($ability = $transition->getAbility()) {
            Gate::authorize($ability, $this->model);
        }

        return $this->model;
    }

    /**
     * Rollback workflow state to initial state.
     */
    public function reset()
    {
        $this->model->setAttribute($this->getAttributeName(), $this->getInitialState());
    }
}
