<?php


use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;
use Illuminate\Database\Eloquent\Model;

class Blueprint extends \Codewiser\Workflow\WorkflowBlueprint
{

    /**
     * Array of available Model Workflow steps. First one is initial
     * @return array|string[]
     * @example [new, review, published, correcting]
     */
    protected function states(): array
    {
        return ['new', 'old'];
    }

    /**
     * Array of allowed transitions between states
     * @return array|Transition
     * @example [[new, review], [review, published], [review, correcting], [correcting, review]]
     */
    protected function transitions(): array
    {
        return [
            Transition::define('new', 'old')
                ->condition(function (Model $model) {
                    if (strlen($model->body) < 1000) {
                        throw new TransitionRecoverableException('Post body is too small. At least 1000 symbols required');
                    }
                })
                ->condition([$this, 'states'])
                ->callback(function (Model $model, $payload) {
                    $model->user->notify(new Notification($model, $payload['attr']));
                })
        ];
    }
}