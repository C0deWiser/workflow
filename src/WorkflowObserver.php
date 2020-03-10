<?php


namespace Codewiser\Workflow;


use Codewiser\Journalism\Journal;
use Codewiser\Journalism\Journalised;
use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Database\Eloquent\Model;

class WorkflowObserver
{
    public function creating(Model $model) {
        /* @var Model|Workflow $model */
        $model->setAttribute($model->workflow()->getAttributeName(), $model->workflow()->getInitialState());
    }

    public function updating(Model $model) {
        /* @var Model|Workflow $model */
        $workflow = $model->workflow();

        if ($model->isDirty($workflow->getAttributeName())) {

            $source = $model->getOriginal($workflow->getAttributeName());
            $target = $model->getAttribute($workflow->getAttributeName());

            $foundSuchTransition = false;
            foreach ($workflow->getTransitions() as $transition) {
                if ($transition->getSource() == $source && $transition->getTarget() == $target) {
                    // We found transition from source to target
                    $foundSuchTransition = $transition;
                    // Check if transition can be performed
                    $transition->execute();
                }
            }
            if (!$foundSuchTransition) {
                throw new WorkflowException('Model can not be transited to given state');
            }

            // Journal this event before `updated`, so the `journalMemo` will be us )
            $model->journalise('transited', [$workflow->getAttributeName() => $target]);
        }
    }
}