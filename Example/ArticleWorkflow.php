<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Charge;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\State;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Support\Collection;

/**
 * @extends WorkflowBlueprint<string>
 */
class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    public function states(): array
    {
        return [
            State::make(Enum::new)
                ->withContext(['comment' => 'nullable']),
            State::make(Enum::review)->attribute('height', 100),
            Enum::published,
            State::make(Enum::correction)->withContext([
                'urgency' => 'integer'
            ]),
            Enum::unreacheable,
            Enum::chargeable,
            Enum::prohibited,
        ];
    }

    public function transitions(): array
    {
        return [
            Transition::make(Enum::new, Enum::review)
                ->as(fn(Article $article) => $article->condition
                    ? 'Bad condition'
                    : 'Good condition'
                )
                ->condition(fn(Article $article) => $article->condition ? 'Incomplete' : null)
                ->attribute('color', 'red'),

            Transition::make(Enum::new, Enum::published)
                ->as('Forbidden transition')
                ->when(fn() => false),

            Transition::make(Enum::review, Enum::published),

            Transition::make(Enum::review, Enum::correction)
                ->withContext(['comment' => 'required'])
                ->saving(function (Article $model, Context $context) {
                    $model->body = $context->data()['comment'];
                }),

            Transition::make(Enum::correction, Enum::review),

            Transition::make(Enum::new, Enum::chargeable)
                ->withContext(['comment' => 'nullable'])
                ->chargeable(Charge::make(
                    function (Article $model) {
                        if (! $model->votes) {
                            $model->votes = new Collection();
                        }

                        return $model->votes->count() / 3;
                    },
                    fn(Article $model, Context $context) => $model->votes->add($context->data()->all())
                )),

            Transition::make(Enum::new, Enum::prohibited)
                ->authorizedBy(fn() => false),
        ];
    }
}
