<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Charge;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;

/**
 * @extends WorkflowBlueprint<string>
 */
class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    protected static int $charge = 0;

    public function userResolver(): \Closure
    {
        return fn() => null;
    }

    public function states(): array
    {
        return [
            Enum::new,
            Enum::review,
            Enum::published,
            Enum::correction,
            Enum::empty,
            Enum::cumulative,
        ];
    }

    public function transitions(): array
    {
        return [
            Transition::make(Enum::new, Enum::review)
                ->before(function (Article $model) {
                    throw new TransitionRecoverableException();
                })
                ->set('color', 'red'),

            Transition::make(Enum::review, Enum::published)->as('Fatal transition')
                ->before(function (Article $model) {
                    throw new TransitionFatalException();
                }),

            Transition::make(Enum::review, Enum::correction)
                ->rules([
                    'comment' => 'required'
                ])
                ->authorizedBy([$this, 'authorize'])
                ->saving(function (Article $model, Context $context) {
                    $model->body = $context->data()['comment'];
                }),

            Transition::make(Enum::correction, Enum::review)
                ->authorizedBy(fn() => false),

            Transition::make(Enum::new, Enum::cumulative)
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
