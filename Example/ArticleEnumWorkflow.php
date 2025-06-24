<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Charge;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;

/**
 * @extends WorkflowBlueprint<Enum>
 */
class ArticleEnumWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    protected static int $charge = 0;

    public function states(): array
    {
        return Enum::cases();
    }

    public function transitions(): array
    {
        return [
            Transition::make(Enum::new, Enum::review)
                ->before(fn() => throw new TransitionRecoverableException())
                ->set('color', 'red'),

            Transition::make(Enum::review, Enum::published)->as('Fatal transition')
                ->before(fn() => throw new TransitionFatalException()),

            Transition::make(Enum::review, Enum::correction)
                ->rules([
                    'comment' => 'required'
                ])
                ->authorizedBy([$this, 'authorize'])
                ->after(function (ArticleWithEnum $model, Context $context) {
                    $model->body = $context->data()->get('comment');
                }),

            Transition::make(Enum::correction, Enum::review)
                ->authorizedBy(fn() => false),

            Transition::make(Enum::new, Enum::cumulative)
                ->chargeable(Charge::make(
                    function () {
                        return self::$charge / 3;
                    },
                    function () {
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
