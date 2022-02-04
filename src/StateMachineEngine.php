<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\HasWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StateMachineEngine
{
    /**
     * @var Model|HasWorkflow
     */
    protected Model $model;

    /**
     * Attribute name. It keeps workflow state.
     *
     * @var string
     */
    protected string $attribute;
    protected WorkflowBlueprint $blueprint;
    protected ?TransitionCollection $transitions = null;

    /**
     * Transition additional context.
     *
     * @var array
     */
    protected array $context = [];

    public function __construct(WorkflowBlueprint $blueprint, Model $model, string $attribute)
    {
        $this->model = $model;
        $this->blueprint = $blueprint;
        $this->attribute = $attribute;
    }

    public function __toString()
    {
        return (string)$this->state();
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
     * @param State|string|null $state
     * @return string
     */
    public function caption(string $state = null): string
    {
        $state = $state ? $this->states()->one($state) : $this->state();
        return $state->caption() ?: __(Str::snake(class_basename($this->blueprint)) . ".states.{$state}");
    }

    /**
     * Get all states of the workflow.
     *
     * @return StateCollection|State[]
     */
    public function states(): StateCollection
    {
        return $this->blueprint->getStates();
    }

    /**
     * Get all transitions in the workflow.
     *
     * @return TransitionCollection|Transition[]
     */
    public function transitions(): TransitionCollection
    {
        if ($this->transitions) {
            return $this->transitions;
        }

        $this->transitions = $this->blueprint->getTransitions()
            ->each(function (Transition $transition) {
                $transition->inject($this->model, $this->attribute);
            });

        return $this->transitions;
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
     * @return State
     */
    public function initial(): State
    {
        return $this->states()->first();
    }

    /**
     * Get the model current state.
     *
     * @return State|null
     */
    public function state(): ?State
    {
        $state = $this->model->getAttribute($this->attribute());

        return $state ? $this->states()->one($state) : null;
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
            } elseif (is_callable($ability)) {
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
        $this->model->setAttribute($this->attribute(), (string)$this->initial());
    }

    /**
     * Get or set transition additional context.
     *
     * @param array|null $context
     * @return array
     */
    public function context(array $context = null): array
    {
        if (is_array($context)) {
            $this->context = $context;
        }

        return $this->context;
    }
}
