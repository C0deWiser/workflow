<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\Transition;

class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    public static bool $enum = true;

    public function states(): array
    {
        if (self::$enum) {
            return \Codewiser\Workflow\Example\State::cases();
        } else {
            return [
                'first',
                'second',
                'recoverable',
                'fatal',
                'callback',
                'deny',
            ];
        }
    }

    public function transitions(): array
    {
        return [
            Transition::make('first', 'recoverable')
                ->before(function (Article $model) {
                    throw new TransitionRecoverableException();
                })
                ->set('color', 'red'),

            Transition::make('first', 'fatal')->as('Fatal transition')
                ->before(function (Article $model) {
                    throw new TransitionFatalException();
                }),

            Transition::make('first', 'callback')
                ->rules([
                    'comment' => 'required'
                ])
                ->authorizedBy([$this, 'authorize'])
                ->after(function (Article $model, array $context) {
                    $model->body = $context['comment'];
                }),

            Transition::make('first', 'deny')
                ->authorizedBy(function (Article $model) {
                    return false;
                }),

            [['callback', 'recoverable', 'fatal'], 'first'],
            // duplication
            Transition::make('fatal', 'first')
        ];
    }

    public function authorize($model): bool
    {
        return true;
    }
}
