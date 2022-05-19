<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\Transition;

class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    protected function states(): array
    {
        return [
            'one',
            State::make('recoverable')->as('Initial state')->set('color', 'red'),
            'fatal',
            'callback',
            'deny'
        ];
    }

    protected function transitions(): array
    {
        return [
            Transition::make('one', 'recoverable')
                ->before(function (Article $model) {
                    throw new TransitionRecoverableException();
                })
                ->set('color', 'red'),

            Transition::make('one', 'fatal')->as('Fatal transition')
                ->before(function (Article $model) {
                    throw new TransitionFatalException();
                }),

            Transition::make('one', 'callback')
                ->rules([])
                ->authorizedBy([$this, 'authorize'])
                ->after(function (Article $model, array $context) {
                    $model->body = $context['comment'];
                }),

            Transition::make('one', 'deny')
                ->authorizedBy(function (Article $model) {
                    return false;
                }),

            ['callback', 'one'],
            ['recoverable', 'one'],
            Transition::make('fatal', 'one')
        ];
    }

    public function authorize($model): bool
    {
        return true;
    }
}
