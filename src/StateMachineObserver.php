<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Initiates State Machine, watches for changes, fires Event.
 *
 * @package Codewiser\Workflow
 */
class StateMachineObserver
{
    /**
     * @param Model|Workflow $model
     * @return bool
     */
    public function creating(Model $model)
    {
        $model->getWorkflowListing()
            ->each(function (StateMachineEngine $engine) use ($model) {
                $model->setAttribute($engine->attribute(), (string)$engine->initial());
            });

        return true;
    }

    /**
     * @param Model|Workflow $model
     * @return bool
     */
    public function updating(Model $model)
    {
        // If one transition is invalid, all update is invalid
        return $model->getWorkflowListing()
            // Rejecting successful validations
            ->reject(function (StateMachineEngine $engine) use ($model) {
                $attribute = $engine->attribute();

                if ($model->isDirty($attribute) &&
                    ($source = $model->getOriginal($attribute)) &&
                    ($target = $model->getAttribute($attribute)) &&
                    ($source != $target)) {

                    $transition = $engine->transitions()
                        ->from($source)->to($target)
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

    /**
     * @param Model|Workflow $model
     */
    public function updated(Model $model)
    {
        $model->getWorkflowListing()
            ->each(function (StateMachineEngine $engine) use ($model) {
                $attribute = $engine->attribute();

                if ($model->wasChanged($attribute) &&
                    ($source = $model->getOriginal($attribute)) &&
                    ($target = $model->getAttribute($attribute)) &&
                    ($source != $target)) {

                    $transition = $engine->transitions()
                        ->from($source)->to($target)
                        ->sole();

                    // Context was validated while `updating`. Just use it
                    $context = $engine->context();

                    // For Transition Observer
                    if (method_exists($model, 'fireTransitionEvent')) {
                        $model->fireTransitionEvent('transited', false, $engine, $transition, $context);
                    }

                    // For Event Listener
                    event(new ModelTransited($model, $engine, $transition, $context));

                    // For Transition Callback
                    $transition->callbacks()
                        ->each(function (\Closure $callback) use ($model, $context) {
                            call_user_func($callback, $model, $context);
                        });
                }
            });
    }
}
