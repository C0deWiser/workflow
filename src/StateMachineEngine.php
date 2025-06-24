<?php


namespace Codewiser\Workflow;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @template TType of \UnitEnum
 */
class StateMachineEngine implements Arrayable
{
    protected ?StateCollection $states = null;

    protected ?TransitionCollection $transitions = null;

    /**
     * @param  WorkflowBlueprint  $blueprint
     * @param  TModel&Model  $model
     * @param  string  $attribute
     */
    public function __construct(public WorkflowBlueprint $blueprint, public Model $model, public string $attribute)
    {
        //
    }

    public function __serialize(): array
    {
        return [
            'blueprint' => serialize($this->blueprint),
            'attribute' => $this->attribute,
            'model'     => get_class($this->model),
            'id'        => $this->model->getKey(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->blueprint = unserialize($data['blueprint']);
        $this->attribute = $data['attribute'];
        $this->model = $data['model']::find($data['id']);
    }

    /**
     * Get an authenticated user for the moment.
     */
    public function getActor(): ?Authenticatable
    {
        return call_user_func($this->blueprint->userResolver());
    }

    /**
     * Get all states of the workflow.
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
     */
    public function transitions(): TransitionCollection
    {
        return $this->state() ? $this->state()->transitions() : TransitionCollection::make();
    }

    /**
     * Init model's workflow with default (or any) state and optional context. Returns Model for you to save it.
     *
     * @param  array  $context
     * @param  null|(TType&\UnitEnum)  $state  Override initial state.
     *
     * @return TModel&Model
     */
    public function init(array $context = [], \UnitEnum $state = null): Model
    {
        // Set initial state
        if ($state) {
            $this->model->setAttribute(
                $this->attribute,
                $state
            );
        }

        // Put context for later use in observer
        $this->setContext($context);

        return $this->model;
    }

    /**
     * Change model's state to a new value, passing optional context. Returns Model for you to save it.
     *
     * @param  TType&\UnitEnum  $state
     * @param  array  $context
     *
     * @return TModel&Model
     * @throws ValidationException
     * @throws ItemNotFoundException
     */
    public function transit(\UnitEnum $state, array $context = []): Model
    {
        // Charging transition?
        if ($transition = $this->transitionTo($state)) {
            if ($charge = $transition->charge()) {
                if ($charge->mayCharge($transition)) {
                    $transition->withContext($context);
                    $charge->charge($transition);
                }
                if (!$charge->charged($transition)) {
                    return $this->model;
                }
            }
        } else {
            throw new ItemNotFoundException();
        }

        // Fire transition
        $this->model->setAttribute(
            $this->attribute,
            $state
        );

        // Put context for later use in observer
        $this->setContext($context);

        return $this->model;
    }

    public function setContext(array $context = []): static
    {
        if (property_exists($this->model, 'transition_context')) {
            $this->model->transition_context = [
                $this->attribute => $context
            ];
        }

        return $this;
    }

    /**
     * Authorize transition to the new state.
     *
     * @param  TType&\UnitEnum  $target
     *
     * @return StateMachineEngine
     * @throws AuthorizationException
     */
    public function authorize(\UnitEnum $target): static
    {
        $transition = $this->transitions()
            ->to($target)
            ->sole();

        if ($ability = $transition->authorization()) {
            if (is_string($ability)) {
                Gate::authorize($ability, [$this->model, $transition]);
            } elseif (is_callable($ability)) {
                if (!call_user_func($ability, $this->model, $transition)) {
                    throw new AuthorizationException();
                }
            }
        }

        return $this;
    }

    /**
     * Get the current state.
     *
     * @return null|State<TType>
     */
    public function state(): ?State
    {
        $value = $this->model->getAttribute($this->attribute);

        return $value ? $this->getStateListing()->one($value) : null;
    }

    /**
     * Check if the state has given value.
     *
     * @param  TType&\UnitEnum  $state
     *
     * @return bool
     */
    public function is(\UnitEnum $state): bool
    {
        return $this->state() && $this->state()->is($state);
    }

    /**
     * Check if the state doesn't have given value.
     *
     * @param  TType&\UnitEnum  $state
     *
     * @return bool
     */
    public function isNot(\UnitEnum $state): bool
    {
        return $this->state() && $this->state()->isNot($state);
    }

    public function toArray(): array
    {
        $state = $this->state() ? $this->state()->toArray() : [];
        $transitions = $this->transitions()->onlyAuthorized()->toArray();

        return $state + ['transitions' => $transitions];
    }

    /**
     * Observer watches for transitions...
     */
    public function observer(): StateMachineObserver
    {
        return new StateMachineObserver($this);
    }

    /**
     * Get the transition from the current state if it exists.
     *
     * @param  TType&\UnitEnum  $target
     *
     * @return null|Transition<TModel, TType>
     */
    public function transitionTo(\UnitEnum $target): ?Transition
    {
        return $this->state()->transitionTo($target);
    }
}
