<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Attributes\Workflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * @template TModel of Model
 * @template TType of \BackedEnum
 * @template TBlueprint of WorkflowBlueprint
 */
class StateMachine implements Arrayable
{
    protected ?StateCollection $states = null;

    protected ?TransitionCollection $transitions = null;

    /**
     * Keep transitions context across app-flow.
     *
     * @var array<string, array>
     */
    public static array $context = [];

    /**
     * Get workflow listing for a model.
     *
     * @return Collection<int, static>
     */
    public static function collect(Model $model): Collection
    {
        $engines = [];

        $reflect = new \ReflectionClass($model);

        foreach ($reflect->getMethods() as $method) {
            $is_workflow = array_filter(
                $method->getAttributes(),
                fn(\ReflectionAttribute $attribute) => $attribute->getName() === Workflow::class
            );

            if ($is_workflow) {
                $engines[] = $method->invoke($model);
            }
        }

        return new Collection($engines);
    }

    /**
     * Try to restore engine for a given blueprint.
     *
     * @param  TModel  $model
     * @param  string  $blueprint  Blueprint or attribute.
     */
    public static function restore(Model $model, string $blueprint): ?static
    {
        return static::collect($model)->first(
            fn(self $engine) => get_class($engine->blueprint) === $blueprint || $engine->attribute === $blueprint
        );
    }

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
    public function getActor(): ?Authorizable
    {
        return call_user_func($this->blueprint->actor());
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
     * @param  array  $context
     * @param  null|TType  $enum  Override initial state.
     *
     * @return TModel
     */
    public function init(array $context = [], \BackedEnum $enum = null): Model
    {
        // Set initial state
        if ($enum) {
            $this->model->setAttribute(
                $this->attribute,
                $enum
            );
        }

        // Put context for later use in observer
        $this->keepContext($context);

        return $this->model;
    }

    /**
     * Change model's state to a new value, passing optional context. Returns Model for you to save it.
     *
     * @param  TType  $enum
     * @param  array  $context
     *
     * @return TModel
     * @throws ValidationException
     * @throws ItemNotFoundException
     */
    public function transit(\BackedEnum $enum, array $context = []): Model
    {
        if ($transition = $this->transitionTo($enum)) {

            // Chargeable transition?
            if ($charge = $transition->charge($this)) {

                $transition->context($context);

                if ($charge->mayCharge($transition)) {
                    $charge->charge($transition);
                }

                if (! $charge->charged($transition)) {
                    return $this->model;
                }
            }
        } else {
            throw new ItemNotFoundException();
        }

        // Fire transition
        $this->model->setAttribute(
            $this->attribute,
            $enum
        );

        // Put context for later use in observer
        $this->keepContext($context);

        return $this->model;
    }

    protected function keepContext(array $context = []): void
    {
        self::$context[$this->attribute] = $context;
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
}
