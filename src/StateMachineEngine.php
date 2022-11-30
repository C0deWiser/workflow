<?php


namespace Codewiser\Workflow;

use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class StateMachineEngine implements Arrayable
{
    protected ?TransitionCollection $transitions = null;
    protected ?StateCollection $states = null;

    /**
     * Transition additional context.
     *
     * @var array
     */
    protected array $context = [];

    public function __construct(
        protected WorkflowBlueprint $blueprint,
        protected Model             $model,
        protected string            $attribute
    )
    {
        //
    }

    /**
     * Get model's Workflow Blueprint.
     */
    public function getBlueprint(): WorkflowBlueprint
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
     * Get possible transitions from the current state.
     *
     * @return TransitionCollection<Transition>
     */
    public function routes(): TransitionCollection
    {
        return $this->state()?->transitions() ?? TransitionCollection::make();
    }

    /**
     * Get or set transition additional context.
     */
    public function context(array $context = null): self|array
    {
        if (is_array($context)) {
            $this->context = $context;

            return $this;
        }

        return $this->context;
    }

    /**
     * Get model attached.
     */
    public function model(): Model
    {
        return $this->model;
    }

    /**
     * Get model's attribute attached.
     */
    public function attribute(): string
    {
        return $this->attribute;
    }

    /**
     * Change model's state to a new value.
     */
    public function moveTo(BackedEnum|string|int $state): void
    {
        $this->model()->setAttribute(
            $this->attribute(),
            $state
        );

        $this->model()->save();
    }

    /**
     * Authorize transition to the new state.
     *
     * @param BackedEnum|string|int $target
     * @return StateMachineEngine
     * @throws AuthorizationException
     */
    public function authorize(BackedEnum|string|int $target): static
    {
        $transition = $this->routes()
            ->to($target)
            ->sole();

        if ($ability = $transition->authorization()) {
            if (is_string($ability)) {
                Gate::authorize($ability, $this->model());
            } elseif (is_callable($ability)) {
                if (!call_user_func($ability, $this->model())) {
                    throw new AuthorizationException();
                }
            }
        }

        return $this;
    }

    /**
     * Get current state.
     */
    public function state(): ?State
    {
        $value = $this->model->getAttribute($this->attribute);

        return $value ? $this->states()->one($value) : null;
    }

    public function toArray(): array
    {
        return ($this->state()?->toArray() ?? [])
        + ['transitions' => $this->routes()->onlyAuthorized()->toArray()];
    }
}
