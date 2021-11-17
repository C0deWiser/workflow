<?php

namespace App;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\Transition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class Blueprint extends \Codewiser\Workflow\WorkflowBlueprint
{
    protected function states(): array
    {
        return [
            State::define('one')->as('Initial state'),
            'recoverable',
            'fatal',
            'callback',
            'deny'
        ];
    }

    protected function transitions(): array
    {
        return [
            Transition::define('one', 'recoverable')
                ->before(function (Post $model) {
                    throw new TransitionRecoverableException();
                }),

            Transition::define('one', 'fatal')->as('Fatal transition')
                ->before(function (Post $model) {
                    throw new TransitionFatalException();
                }),

            Transition::define('one', 'callback')
                ->requires('comment')
                ->after(function (Post $model, array $context) {
                    $model->body = $context['comment'];
                }),

            Transition::define('one', 'deny')
                ->authorizedBy(function (Post $model) {
                    return false;
                }),

            Transition::define('callback', 'one'),
            Transition::define('recoverable', 'one'),
            Transition::define('fatal', 'one')
        ];
    }
}