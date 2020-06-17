<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Initiates State Machine, watch for changes, fires Event
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
     * @throws WorkflowException
     */
    public function updating(Model $model)
    {
        foreach ($model->getDirty() as $attribute => $value) {
            if ($workflow = $model->workflow($attribute)) {
                // Workflow attribute is dirty
                $class = class_basename($model);
                throw new WorkflowException("Property `{$class}->{$attribute}` is protected.".
                    " Use {$class}->workflow('{$attribute}')->transit('{$value}') to change state.");
            }
        }
    }
}