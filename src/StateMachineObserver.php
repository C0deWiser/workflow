<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Exceptions\StateMachineConsistencyException;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Exceptions\WorkflowException;
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
                $model->setAttribute($engine->attribute(), $engine->initial());
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

                if ($model->isDirty($attribute)) {

                    $transition = $engine->transitions()
                        ->from($model->getOriginal($attribute))
                        ->to($model->getAttribute($attribute))
                        ->valid()
                        ->allowed()
                        // It may be not
                        ->first();

                    if ($transition) {
                        // For Transition Observer
                        if (method_exists($model, 'fireTransitionEvent')) {
                            if ($model->fireTransitionEvent('transiting', true, $engine, $transition) === false) {
                                return false;
                            }
                        }
                    } else {
                        // Transition doesnt exist
                        return false;
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

                if ($model->wasChanged($attribute)) {

                    $transition = $engine->transitions()
                        ->from($model->getOriginal($attribute))
                        ->to($model->getAttribute($attribute))
                        ->valid()
                        ->allowed()
                        // It must be!
                        ->sole();

                    // For Transition Observer
                    if (method_exists($model, 'fireTransitionEvent')) {
                        $model->fireTransitionEvent('transited', false, $engine, $transition);
                    }

                    // For Event Listener
                    event(new ModelTransited($model, $engine, $transition));

                    // For Transition Callback
                    $transition->callbacks()
                        ->each(function (\Closure $callback) use ($model) {
                            call_user_func($callback, $model);
                        });
                }
            });
    }
}
