<?php

namespace App;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\Transition;

class Blueprint extends \Codewiser\Workflow\WorkflowBlueprint
{
    protected function states(): array
    {
        return [
            'one',
            State::define('recoverable')->as('Initial state')->set('color', 'red'),
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
                })
                ->set('color', 'red'),

            Transition::define('one', 'fatal')->as('Fatal transition')
                ->before(function (Post $model) {
                    throw new TransitionFatalException();
                }),

            Transition::define('one', 'callback')
                ->rules([
                    'comment' => 'required|string'
                ])
                ->authorizedBy([$this, 'authorize'])
                ->after(function (Post $model, array $context) {
                    $model->body = $context['comment'];
                }),

            Transition::define('one', 'deny')
                ->authorizedBy(function (Post $model) {
                    return false;
                }),

            ['callback', 'one'],
            ['recoverable', 'one'],
            Transition::define('fatal', 'one')
        ];
    }

    public function authorize($model): bool
    {
        return true;
    }
}
