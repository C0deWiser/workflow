<?php

namespace App;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class Blueprint extends \Codewiser\Workflow\WorkflowBlueprint
{
    protected function states(): array
    {
        return ['one', 'two', 'three', 'four'];
    }

    protected function transitions(): array
    {
        return [
            Transition::define('one', 'two')
                ->condition(function (Post $model) {
                    throw new TransitionRecoverableException();
                })
                ->authorize(function (Post $model) {

                }),

            Transition::define('one', 'three')
                ->condition(function (Post $model) {
                    throw new TransitionFatalException('Fatal');
                }),

            Transition::define('one', 'four')
                ->requires('comment')
                ->callback(function (Post $model, $payload) {
                    $model->body = 'sent';
                }),

            Transition::define('four', 'three'),
            Transition::define('two', 'three'),
            Transition::define('three', 'one')
        ];
    }
}