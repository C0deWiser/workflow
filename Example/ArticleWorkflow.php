<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Charge;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;

class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    protected static int $charge = 0;

    public function states(): array
    {
        return [
            'new',
            'review',
            'published',
            'correction',
            'empty',
            'cumulative',
        ];
    }

    public function transitions(): array
    {
        return [
            Transition::make('new', 'review')
                ->before(function (Article $model) {
                    throw new TransitionRecoverableException();
                })
                ->set('color', 'red'),

            Transition::make('review', 'published')->as('Fatal transition')
                ->before(function (Article $model) {
                    throw new TransitionFatalException();
                }),

            Transition::make('review', 'correction')
                ->rules([
                    'comment' => 'required'
                ])
                ->authorizedBy([$this, 'authorize'])
                ->after(function (Article $model, array $context) {
                    $model->body = $context['comment'];
                }),

            Transition::make('correction', 'review')
                ->authorizedBy(function () {
                    return false;
                }),

            Transition::make('new', 'cumulative')
                ->chargeable(Charge::make(
                    function (Article $model) {
                        return self::$charge / 3;
                    },
                    function (Article $model) {
                        self::$charge++;
                    }
                ))
        ];
    }

    public function authorize($model): bool
    {
        return true;
    }
}
