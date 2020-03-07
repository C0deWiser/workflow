<?php
namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait Workflow
{
    /**
     * Returns model workflow
     * @return WorkflowBlueprint
     */
    abstract public function workflow(): WorkflowBlueprint;

    protected static function booted()
    {
        static::creating(function (Model $model) {
            /* @var Workflow $model */
            $model->setAttribute($model->workflow()->getAttribute(), $model->workflow()->getInitialState());
        });

        static::updating(function (Model $model) {
            /* @var Workflow $model */

            if ($model->isDirty($model->workflow()->getAttribute())) {

                $source = $model->getOriginal($model->workflow()->getAttribute());
                $target = $model->getAttribute($model->workflow()->getAttribute());

                foreach ($model->workflow()->getTransitions() as $i) {
                    if ($i->getSource() == $source && $i->getTarget() == $target) {
                        // We found transition from source to target
                        return $i->execute();
                    }
                }
                throw new WorkflowException('Model can not be transited to given state');
            }
        });
    }

    public function scopeWorkflow(Builder $query, $state)
    {
        return $query->where($this->workflow()->getAttribute(), $state);
    }
}