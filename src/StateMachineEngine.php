<?php


namespace Codewiser\Workflow;

use BackedEnum;
use Codewiser\Workflow\Traits\HasWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Illuminate\Support\Str;

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
     * Get possible transitions from current state.
     *
     * @return TransitionCollection<Transition>
     */
    public function getRoutes(): TransitionCollection
    {
        return $this->getCurrent()?->transitions() ?? TransitionCollection::make();
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
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @return string
     */
    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * Authorize transition to the new state.
     *
     * @param BackedEnum|string|int $target
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     * @throws AuthorizationException
     */
    public function authorize(BackedEnum|string|int $target): static
    {
        $transition = $this->getRoutes()
            ->to($target)
            ->sole();

        if ($ability = $transition->authorization()) {
            if (is_string($ability)) {
                Gate::authorize($ability, $this->getModel());
            } elseif (is_callable($ability)) {
                if (!call_user_func($ability, $this->getModel())) {
                    throw new AuthorizationException();
                }
            }
        }

        return $this;
    }

    public function getCurrent(): ?State
    {
        $value = $this->model->getAttribute($this->attribute);

        return $value ? $this->states()->one($value) : null;
    }

    public function toArray()
    {
        return $this->getCurrent()?->toArray() +
            [
                'transitions' => $this->getRoutes()->onlyAuthorized()->toArray()
            ];
    }
}
