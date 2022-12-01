<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Initiates State Machine, watches for changes, fires Event.
 */
class StateMachineObserver
{
    /**
     * @param Model $model
     * @return Collection<StateMachineEngine>
     * @throws ReflectionException
     */
    private function getWorkflowListing(Model $model): Collection
    {
        $blueprints = [];

        $reflect = new ReflectionClass($model);
        foreach ($reflect->getMethods() as $method) {

            if ($method->isPublic()) {
                $return_type = $method->getReturnType();

                if ($return_type instanceof ReflectionNamedType &&
                    $return_type->getName() == StateMachineEngine::class) {
                    $blueprints[] = $method->invoke($model);
                } elseif ($return_type instanceof ReflectionUnionType &&
                    $return_type->getTypes() == [StateMachineEngine::class]) {
                    $blueprints[] = $method->invoke($model);
                } elseif ($return_type instanceof ReflectionIntersectionType &&
                    $return_type->getTypes() == [StateMachineEngine::class]) {
                    $blueprints[] = $method->invoke($model);
                }
            }
        }

        return collect($blueprints);
    }

    public function saved(Model $model): void
    {
        $this->getWorkflowListing($model)
            ->each(function (StateMachineEngine $engine) {
                if (!$this->wasTransited($engine)) {
                    // Do not listen to transit events!
                    $this->subscribers('saved', $engine);
                }
            });
    }

    public function creating(Model $model): bool
    {
        $this->getWorkflowListing($model)
            ->each(function (StateMachineEngine $engine) use ($model) {
                $model->setAttribute($engine->attribute, $engine->getStateListing()->initial()->value);
            });

        return true;
    }

    public function created(Model $model): void
    {
        $this->getWorkflowListing($model)
            ->each(function (StateMachineEngine $engine) {
                // For Event Listener
                event(new ModelInitialized($engine));

                $this->subscribers('created', $engine);
            });
    }

    public function updating(Model $model): bool
    {
        // If one transition is invalid, all update is invalid
        return $this->getWorkflowListing($model)
            // Rejecting successful validations
            ->reject(function (StateMachineEngine $engine) use ($model) {

                if ($transition = $this->nowTransiting($engine)) {

                    // May throw an Exception
                    $transition->validate();

                    // For Transition Observer
                    if (method_exists($model, 'fireTransitionEvent')) {
                        if ($model->fireTransitionEvent('transiting', true, $engine, $transition) === false) {
                            return false;
                        }
                    }
                }

                return true;
            })
            // Empty means there are no failures
            ->isEmpty();
    }

    public function updated(Model $model): void
    {
        $this->getWorkflowListing($model)
            ->each(function (StateMachineEngine $engine) use ($model) {

                if ($transition = $this->wasTransited($engine)) {

                    // For Transition Observer
                    if (method_exists($model, 'fireTransitionEvent')) {
                        $model->fresh()->fireTransitionEvent('transited', false, $engine, $transition);
                    }

                    // For Event Listener
                    event(new ModelTransited($engine, $transition));

                    // For Transition Callback
                    $transition->callbacks()
                        ->each(function ($callback) use ($model, $transition) {
                            call_user_func($callback, $model->fresh(), $transition->context());
                        });
                } else {
                    // Do not listen to transit events!
                    $this->subscribers('updated', $engine);
                }
            });
    }

    protected function nowTransiting(StateMachineEngine $engine):?Transition
    {
        $model = $engine->model;
        $attribute = $engine->attribute;

        if ($model->isDirty($attribute) &&
            ($source = $model->getOriginal($attribute)) &&
            ($target = $model->getAttribute($attribute)) &&
            $source != $target) {

            return $engine->getTransitionListing()
                ->from($source)
                ->to($target)
                // Transition must exist
                ->sole()
                // Pass context to transition for validation. May throw an Exception
                ->context($this->context($model, $attribute));
        }

        return null;
    }

    protected function wasTransited(StateMachineEngine $engine):?Transition
    {
        $model = $engine->model;
        $attribute = $engine->attribute;

        if ($model->wasChanged($attribute) &&
            ($source = $model->getOriginal($attribute)) &&
            ($target = $model->getAttribute($attribute)) &&
            $source != $target) {

            return $engine->getTransitionListing()
                ->from($source)
                ->to($target)
                // Transition must exist
                ->sole()
                // Pass context to transition, so it will be accessible in events.
                ->context($this->context($model, $attribute));
        }

        return null;
    }

    protected function context(Model $model, string $attribute): array
    {
        if (property_exists($model, 'transition_context')) {
            if (isset($model->transition_context[$attribute])) {
                return $model->transition_context[$attribute];
            }
        }

        return [];
    }

    protected function subscribers(string $event, StateMachineEngine $engine): void
    {
        $engine
            ->transitions()
            ->listeningTo($event)
            ->each(function (Transition $transition) use ($engine) {
                $callback = $transition->listener('updated');
                if (is_callable($callback)) {
                    call_user_func($callback, $engine->model, $transition);
                }
            });;
    }
}
