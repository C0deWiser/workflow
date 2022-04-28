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

    /**
     * Transition additional context.
     *
     * @var array
     */
    protected array $context = [];

    /**
     * @param WorkflowBlueprint $blueprint
     * @param Model $model
     * @param string $attribute It keeps state value in a Model.
     */
    public function __construct(
        protected WorkflowBlueprint $blueprint,
        protected Model             $model,
        protected string            $attribute)
    {
    }

    public function __toString()
    {
        return (string)$this->state();
    }

    /**
     * Get model's Workflow Blueprint.
     */
    public function blueprint(): WorkflowBlueprint
    {
        return $this->blueprint;
    }

    /**
     * Get (current or any) state caption trans string.
     */
    public function caption(State|string $state = null): string
    {
        $state = $state ? $this->states()->one($state) : $this->state();

        return $state->caption() ?: Str::snake(class_basename($this->blueprint)) . ".states.{$state}";
    }

    /**
     * Get all states of the workflow.
     *
     * @return StateCollection<State>
     */
    public function states(): StateCollection
    {
        return $this->blueprint->getStates();
    }

    /**
     * Get all transitions in the workflow.
     *
     * @return TransitionCollection<Transition>
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
     * @return TransitionCollection<Transition>
     */
    public function routes(): TransitionCollection
    {
        return $this->transitions()
            ->from($this->state())
            ->withoutForbidden();
    }

    /**
     * Get available transition to the given state.
     */
    public function routeTo(State|string $state): ?Transition
    {
        return $this
            ->routes()
            ->first(function (Transition $transition) use ($state) {
                return $transition->target() == (string)$state;
            });
    }

    /**
     * Get workflow attribute name.
     */
    public function attribute(): string
    {
        return $this->attribute;
    }

    /**
     * Get workflow initial state.
     */
    public function initial(): State
    {
        return $this->states()->first();
    }

    /**
     * Get model's current state.
     */
    public function state(): ?State
    {
        $state = $this->model->getAttribute($this->attribute());

        return $state ? $this->states()->one($state) : null;
    }

    /**
     * Authorize transition to the new state.
     *
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     * @throws AuthorizationException
     */
    public function authorize(string $target): self
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
    public function reset(): void
    {
        $this->model->setAttribute($this->attribute(), (string)$this->initial());
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
}
