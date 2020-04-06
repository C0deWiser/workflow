<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Watch for State Machine consistency
 * @package Codewiser\Workflow
 */
class StateMachineObserver
{
    /**
     * @param Model|Workflow $model
     */
    public function creating(Model $model)
    {
        foreach ($model->getWorkflowListing() as $workflow) {
            // Set initial value for every column that holds workflow state
            $model->setAttribute($workflow->getAttributeName(), $workflow->getInitialState());
        }
    }

    /**
     * @param Model|Workflow $model
     */
    public function updating(Model $model)
    {
        foreach ($model->getDirty() as $attribute => $value) {
            if ($workflow = $model->workflow($attribute)) {
                // Workflow attribute is dirty
                event(new ModelTransited($model, $attribute, $value));
            }
        }
    }
}