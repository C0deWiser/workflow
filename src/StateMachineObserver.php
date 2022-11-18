<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Traits\HasWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Initiates State Machine, watches for changes, fires Event.
 */
class StateMachineObserver
{
    /**
     * @param Model $model
     * @return Collection<StateMachineEngine>
     */
    private function getWorkflowListing(Model $model): Collection
    {
        $blueprints = [];

        $reflect = new \ReflectionClass($model);
        foreach ($reflect->getMethods() as $method) {

            if ($method->isPublic()) {
                $return_type = $method->getReturnType();

                if ($return_type instanceof \ReflectionNamedType &&
                    $return_type->getName() == StateMachineEngine::class) {
                    $blueprints[] = $method->invoke($model);
                } elseif ($return_type instanceof \ReflectionUnionType &&
                    $return_type->getTypes() == [StateMachineEngine::class]) {
                    $blueprints[] = $method->invoke($model);
                } elseif ($return_type instanceof \ReflectionIntersectionType &&
                    $return_type->getTypes() == [StateMachineEngine::class]) {
                    $blueprints[] = $method->invoke($model);
                }
            }
        }

        return collect($blueprints);
    }

    public function creating(Model $model): bool
    {
        $this->getWorkflowListing($model)
            ->each(function (StateMachineEngine $engine) use ($model) {
                $model->setAttribute($engine->getAttribute(), $engine->states()->initial()->state);
            });

        return true;
    }

    public function created(Model $model): void
    {
        $this->getWorkflowListing($model)
            ->each(function (StateMachineEngine $engine) {
                // For Event Listener
                event(new ModelInitialized($engine));
            });
    }

    public function updating(Model $model): bool
    {
        // If one transition is invalid, all update is invalid
        return $this->getWorkflowListing($model)
            // Rejecting successful validations
            ->reject(function (StateMachineEngine $engine) use ($model) {

                $attribute = $engine->getAttribute();

                if (
                    $model->isDirty($attribute) &&
                    ($source = $model->getOriginal($attribute)) &&
                    ($target = $model->getAttribute($attribute))
                ) {

                    $transition = $engine->transitions()
                        ->from($source)
                        ->to($target)
                        // Find or die!
                        ->sole()
                        // May throw an Exception
                        ->validate();

                    // Pass context to transition for validation. May throw an Exception
                    $transition->context($engine->context());

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

                $attribute = $engine->getAttribute();

                if (
                    $model->wasChanged($attribute) &&
                    ($source = $model->getOriginal($attribute)) &&
                    ($target = $model->getAttribute($attribute))
                ) {

                    $transition = $engine->transitions()
                        ->from($source)
                        ->to($target)
                        ->sole();

                    // Context was validated while `updating`. Just use it
                    $transition->context($engine->context());

                    // For Transition Observer
                    if (method_exists($model, 'fireTransitionEvent')) {
                        $model->fresh()->fireTransitionEvent('transited', false, $engine, $transition);
                    }

                    // For Event Listener
                    event(new ModelTransited($engine, $transition));

                    // For Transition Callback
                    $transition->callbacks()
                        ->each(function ($callback) use ($model, $engine) {
                            call_user_func($callback, $model->fresh(), $engine->context());
                        });
                }
            });
    }
}
