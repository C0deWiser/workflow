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

    public function __construct(
        readonly public WorkflowBlueprint $blueprint,
        readonly public Model             $model,
        readonly public string            $attribute
    )
    {
        //
    }

    /**
     * Get all states of the workflow.
     *
     * @return StateCollection<State>
     */
    public function getStateListing(): StateCollection
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
    public function getTransitionListing(): TransitionCollection
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
    public function transitions(): TransitionCollection
    {
        return $this->state()?->transitions() ?? TransitionCollection::make();
    }

    /**
     * Change model's state to a new value, passing optional context.
     */
    public function transit(BackedEnum|string|int $state, array $context = []): void
    {
        $this->model->setAttribute(
            $this->attribute,
            $state
        );

        if (property_exists($this->model, 'transition_context')) {
            $this->model->transition_context = [
                $this->attribute => $context
            ];
        }

        $this->model->save();
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
        $transition = $this->transitions()
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
     * Get current state.
     */
    public function state(): ?State
    {
        $value = $this->model->getAttribute($this->attribute);

        return $value ? $this->getStateListing()->one($value) : null;
    }

    /**
     * Check if state has given value.
     *
     * @param BackedEnum|string|int $state
     * @return bool
     */
    public function is(BackedEnum|string|int $state): bool
    {
        return $this->state()?->is($state);
    }

    public function toArray(): array
    {
        return ($this->state()?->toArray() ?? [])
            + ['transitions' => $this->transitions()->onlyAuthorized()->toArray()];
    }
}
