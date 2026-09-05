<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Attributes\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;

/**
 * Discovers #[Workflow] methods of a model and restores engines.
 */
class StateMachineResolver
{
    /**
     * #[Workflow] methods of a class, resolved and cached once.
     *
     * @var array<class-string, array<int, ReflectionMethod>>
     */
    protected array $workflowMethods = [];

    /**
     * Get #[Workflow] methods of the model class.
     *
     * @param  class-string  $class
     * @return array<int, ReflectionMethod>
     */
    public function workflowMethods(string $class): array
    {
        return $this->workflowMethods[$class] ??= array_values(array_filter(
            (new ReflectionClass($class))->getMethods(),
            fn(ReflectionMethod $method) => (bool) $method->getAttributes(Workflow::class)
        ));
    }

    /**
     * Model attribute a method is bound to: declared in a #[Workflow] attribute
     * or, by default, the name of the method itself.
     */
    public function attributeOf(ReflectionMethod $method): string
    {
        $arguments = $method->getAttributes(Workflow::class)[0]->getArguments();

        $attribute = $arguments['attribute'] ?? $arguments[0] ?? null;

        return $attribute ?? $method->getName();
    }

    /**
     * Get workflow listing for a model.
     *
     * @return Collection<int, StateMachine>
     */
    public function collect(Model $model): Collection
    {
        return new Collection(
            array_map(
                fn(ReflectionMethod $method) => $method->invoke($model),
                $this->workflowMethods(get_class($model))
            )
        );
    }

    /**
     * Try to restore engine for a given model attribute, storing its state.
     *
     * Only the workflow, bound to the attribute, is instantiated.
     * A WorkflowBlueprint class name is accepted as a legacy value.
     *
     * @param  Model  $model
     * @param  string  $blueprint  Attribute name (or Blueprint class, legacy).
     */
    public function restore(Model $model, string $blueprint): ?StateMachine
    {
        foreach ($this->workflowMethods(get_class($model)) as $method) {

            // Legacy: blueprint class name—must build an engine to know its blueprint
            if (is_subclass_of($blueprint, WorkflowBlueprint::class)) {
                $engine = $method->invoke($model);

                if ($engine->blueprint instanceof $blueprint) {
                    return $engine;
                }
            }
            // Current: attribute name—declared in the attribute, no engine built in vain
            elseif ($this->attributeOf($method) === $blueprint) {
                return $method->invoke($model);
            }
        }

        return null;
    }
}