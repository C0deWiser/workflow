<?php


namespace Codewiser\Workflow;


use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Forbid State Machine changes
 * @package Codewiser\Workflow
 */
class StateMachineProtector extends StateMachineObserver
{
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