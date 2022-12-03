<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;

class ArticleEnumWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    public function states(): array
    {
        return Enum::cases();
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
                ->after(function (Article $model, array $context) {
                    $model->body = $context['comment'];
                }),

            Transition::make(Enum::correction, Enum::review)
                ->authorizedBy(function (Article $model) {
                    return false;
                }),

        ];
    }

    public function authorize($model): bool
    {
        return true;
    }
}
