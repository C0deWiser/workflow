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
     * Get (current or any) state caption trans string.
     *
     * @param string|null $state
     * @return string
     */
    public function caption(string $state = null):string
    {
        $state = $state ?: $this->state();
        return __(Str::snake(class_basename($this->blueprint)) . ".states.{$state}");
    }

    /**
     * Get all states of the workflow.
     *
     * @return Collection|string[]
     */
    public function states(): Collection
    {
        return $this->blueprint->getStates();
    }

    /**
     * Get all transitions in the workflow.
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
     * Get proper ways out from the current state.
     *
     * @return TransitionCollection
     */
    public function channels(): TransitionCollection
    {
        return $this->transitions()
            ->from($this->state())
            ->withoutForbidden();
    }

    /**
     * Get workflow attribute name.
     *
     * @return string
     */
    public function attribute(): string
    {
        return $this->attribute;
    }

    /**
     * Get workflow initial state.
     *
     * @return string
     */
    public function initial(): string
    {
        return $this->states()->first();
    }

    /**
     * Get the model current state.
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
        $transition = $this->transitions()
            ->from($this->state())
            ->to($target)
            ->sole();

        if ($ability = $transition->authorization()) {
            if (is_string($ability)) {
                Gate::authorize($ability, $this->model);
            }
            if ($ability instanceof \Closure) {
                if (!call_user_func($ability, $this->model)) {
                    throw new AuthorizationException();
                }
            }
        }

        return $this;
    }

    /**
     * Reset workflow to the initial state.
     */
    public function reset()
    {
        $this->model->setAttribute($this->attribute(), $this->initial());
    }
}
