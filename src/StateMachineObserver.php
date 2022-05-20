<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
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

        foreach ($model->getCasts() as $attribute => $caster) {
            if (is_subclass_of($caster, WorkflowBlueprint::class)) {
                if ($state = $model->getAttribute($attribute)) {
                    /* @var State $state */
                    $blueprints[$attribute] = $state->engine();
                } else {
                    $blueprints[$attribute] = $caster::engine($model, $attribute);
                }
            }
        }

        return collect($blueprints);
    }

    public function creating(Model $model): bool
    {
        $this->getWorkflowListing($model)
            ->each(function (StateMachineEngine $engine, $attribute) use ($model) {
                $model->setAttribute($attribute, $engine->initial());
            });

        return true;
    }

    public function created(Model $model): void
    {
        $this->getWorkflowListing($model)
            ->each(function (StateMachineEngine $engine, $attribute) use ($model) {
                // For Event Listener
                event(new ModelInitialized($model, $engine));
            });
    }

    public function updating(Model $model): bool
    {
        // If one transition is invalid, all update is invalid
        return $this->getWorkflowListing($model)
            // Rejecting successful validations
            ->reject(function (StateMachineEngine $engine, $attribute) use ($model) {

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
            ->each(function (StateMachineEngine $engine, $attribute) use ($model) {

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
                    event(new ModelTransited($model->fresh(), $engine, $transition));

                    // For Transition Callback
                    $transition->callbacks()
                        ->each(function ($callback) use ($model, $engine) {
                            call_user_func($callback, $model->fresh(), $engine->context());
                        });
                }
            });
    }
}
