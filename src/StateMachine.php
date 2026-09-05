<?php


namespace Codewiser\Workflow;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ItemNotFoundException;

/**
 * @template TModel of Model
 * @template TType of \BackedEnum
 * @template TBlueprint of WorkflowBlueprint
 */
class StateMachine implements Arrayable
{
    protected ?StateCollection $states = null;

    protected ?TransitionCollection $transitions = null;

    protected ?Factory $validators = null;

    /**
     * @param  TBlueprint  $blueprint
     * @param  TModel  $model
     * @param  string  $attribute
     */
    public function __construct(
        public WorkflowBlueprint $blueprint,
        public Model $model,
        public string $attribute
    ) {
        //
    }

    public function __serialize(): array
    {
        return [
            'blueprint' => get_class($this->blueprint),
            'attribute' => $this->attribute,
            'model'     => get_class($this->model),
            'id'        => $this->model->getKey(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->blueprint = app($data['blueprint']);
        $this->attribute = $data['attribute'];
        $this->model = $data['model']::find($data['id']);
    }

    /**
     * Get all states of the workflow.
     */
    public function getStateListing(): StateCollection
    {
        if (! $this->states) {
            $this->states = StateCollection::make($this->blueprint->states())->injectWith($this);
        }

        return $this->states;
    }

    /**
     * Get all transitions in the workflow.
     */
    public function getTransitionListing(): TransitionCollection
    {
        if (! $this->transitions) {
            $this->transitions = TransitionCollection::make($this->blueprint->transitions())->injectWith($this);
        }

        return $this->transitions;
    }

    /**
     * Get possible transitions from the current state.
     */
    public function transitions(): TransitionCollection
    {
        return $this->state() ? $this->state()->transitions() : new TransitionCollection();
    }

    /**
     * Init model's workflow with default (or any) state and optional context. Returns Model for you to save it.
     *
     * @param  array  $userdata
     * @param  null|TType  $enum  Override initial state.
     *
     * @return TModel
     */
    public function init(array $userdata = [], \BackedEnum $enum = null): Model
    {
        // Put context for later validation in observer
        $this->keepUserdata($userdata);

        // Set initial state if forced
        if ($enum) {
            $this->model->setAttribute(
                $this->attribute,
                $enum
            );
        }

        return $this->model;
    }

    /**
     * Change model's state to a new value, passing optional context. Returns Model for you to save it.
     *
     * @param  TType  $enum
     * @param  array  $userdata
     *
     * @return TModel
     * @throws ItemNotFoundException
     */
    public function transit(\BackedEnum $enum, array $userdata = []): Model
    {
        if ($transition = $this->transitionTo($enum)) {

            // Chargeable transition?
            if ($charger = $transition->charger($this)) {

                // Charge transition if allowed
                if ($charger->mayCharge($transition)) {
                    $charger->charge($transition, $userdata);
                }

                // Interrupt updating model if transition not fully charged
                if (! $charger->isCharged($transition)) {
                    return $this->model;
                }
            }
        } else {
            throw new ItemNotFoundException();
        }

        // Put context for later validation in observer
        $this->keepUserdata($userdata);

        $this->model->setAttribute(
            $this->attribute,
            $enum
        );

        return $this->model;
    }

    /**
     * Authorize transition to the new state.
     *
     * @param  TType  $enum
     *
     * @throws AuthorizationException
     */
    public function authorize(\BackedEnum $enum): static
    {
        $this->transitions()
            ->to($enum)
            ->sole()
            ->authorize();

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
     * @param  TType  $enum
     */
    public function is(\BackedEnum $enum): bool
    {
        return $this->state()?->is($enum);
    }

    /**
     * Check if the state doesn't have given value.
     *
     * @param  TType  $enum
     */
    public function isNot(\BackedEnum $enum): bool
    {
        return ! $this->state() || $this->state()->isNot($enum);
    }

    public function toArray(): array
    {
        $state = $this->state()?->toArray() ?? [];
        $transitions = $this->transitions()->authorized()->toArray();

        return $state + ['transitions' => $transitions];
    }

    /**
     * Get the transition from the current state if it exists.
     *
     * @param  TType  $enum
     *
     * @return null|Transition<TModel, TType>
     */
    public function transitionTo(\BackedEnum $enum): ?Transition
    {
        return $this->state()->transitionTo($enum);
    }

    # Userdata

    /**
     * Keep transitions context across app-flow.
     *
     * @var array<string, array>
     */
    protected static array $userdata = [];

    /**
     * Store userdata for later use.
     */
    public function keepUserdata(array $userdata = []): void
    {
        static::$userdata[$this->attribute] = $userdata;
    }

    public function userdata(): array
    {
        return static::$userdata[$this->attribute] ?? [];
    }

    /**
     * Set a validator factory, used to validate user data of chargeable transitions.
     * By default, a factory is resolved from the application container.
     */
    public function validateWith(Factory $validators): static
    {
        $this->validators = $validators;

        return $this;
    }

    /**
     * Get a validator factory, if any available.
     *
     * @internal
     */
    public function validators(): ?Factory
    {
        if (is_null($this->validators) && function_exists('app')) {

            $factory = app()->bound(Factory::class)
                ? app(Factory::class)
                : null;

            $this->validators = $factory;
        }

        return $this->validators;
    }

}
