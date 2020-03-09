<?php
namespace Codewiser\Workflow\Traits;

use Codewiser\Journalism\Journal;
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
            $model->setAttribute($model->workflow()->getAttributeName(), $model->workflow()->getInitialState());
        });

        static::updating(function (Model $model) {
            /* @var Workflow $model */
            $workflow = $model->workflow();

            if ($model->isDirty($workflow->getAttributeName())) {

                $source = $model->getOriginal($workflow->getAttributeName());
                $target = $model->getAttribute($workflow->getAttributeName());

                $foundSuchTransition = false;
                foreach ($workflow->getTransitions() as $transition) {
                    if ($transition->getSource() == $source && $transition->getTarget() == $target) {
                        // We found transition from source to target
                        $foundSuchTransition = $transition;
                        $transition->execute();
                    }
                }
                if (!$foundSuchTransition) {
                    throw new WorkflowException('Model can not be transited to given state');
                }

                Journal::log('transited', $model, json_encode($workflow->getTransitionComment()));
            }
        });
    }

    public function scopeWorkflow(Builder $query, $state)
    {
        return $query->where($this->workflow()->getAttributeName(), $state);
    }
}