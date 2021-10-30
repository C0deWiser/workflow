<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Auth\Access\AuthorizationException;
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
        return $this->state();
    }

    /**
     * Get State Machine Blueprint.
     *
     * @return WorkflowBlueprint
     */
    public function blueprint(): WorkflowBlueprint
    {
        return $this->blueprint;
    }

    /**
     * Get human readable [current or any] state caption.
     *
     * @param null|string $state
     * @return array|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public function caption($state = null)
    {
        $state = $state ?: $this->state();
        return trans(Str::snake(class_basename($this->blueprint)) . ".states.{$state}");
    }

    /**
     * Array of the model available workflow states.
     *
     * @return Collection|string[]
     */
    public function states(): Collection
    {
        return $this->blueprint->getStates();
    }

    /**
     * Array of allowed transitions between states.
     *
     * @return TransitionCollection
     */
    public function transitions(): TransitionCollection
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
    public function relevant(): TransitionCollection
    {
        return $this->transitions()
            ->from($this->state())
            ->valid();
    }

    /**
     * Workflow attribute name.
     *
     * @return string
     */
    public function attribute(): string
    {
        return $this->attribute;
    }

    /**
     * Workflow initial state.
     *
     * @return string
     */
    public function initial(): string
    {
        return $this->states()->first();
    }

    /**
     * Model current state.
     *
     * @return string|null
     */
    public function state(): ?string
    {
        return $this->model->getAttribute($this->attribute());
    }

    /**
     * Authorize transition to the new state.
     *
     * @return $this
     * @throws \Illuminate\Support\ItemNotFoundException
     * @throws \Illuminate\Support\MultipleItemsFoundException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize(string $target): StateMachineEngine
    {
        $transition = $this->relevant()
            ->to($target)
            ->sole();

        if ($ability = $transition->authorization()) {
            if (is_string($ability)) {
                Gate::authorize($ability, $this->model);
            }
            if (is_callable($ability)) {
                if (!call_user_func($ability, $this->model)) {
                    throw new AuthorizationException();
                }
            }
        }

        return $this;
    }

    /**
     * Rollback workflow state to initial state.
     */
    public function reset()
    {
        $this->model->setAttribute($this->attribute(), $this->initial());
    }
}
