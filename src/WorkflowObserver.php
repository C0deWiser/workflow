<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Model;

class WorkflowObserver
{
    /**
     * @param Model|Workflow $model
     */
    public function creating(Model $model)
    {
        foreach (array_keys($model->getAttributes()) as $attribute) {
            if ($workflow = $model->workflow($attribute)) {
                // Set initial value for every column that holds workflow state
                $model->setAttribute($attribute, $workflow->getInitialState());
            }
        }
    }

    /**
     * @param Model|Workflow $model
     * @throws Exceptions\WorkflowException
     */
    public function updating(Model $model)
    {
        foreach ($model->getDirty() as $attribute => $value) {
            if ($workflow = $model->workflow($attribute)) {
                // Workflow attribute is dirty

                // Rollback to original value
                $model->setAttribute($attribute, $model->getOriginal($attribute));
                // And utilize workflow transit() method
                // It will check state machine consistency and preconditions
                $workflow->transit($value);
            }
        }
    }
}