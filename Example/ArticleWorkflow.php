<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Charge;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Support\Collection;

/**
 * @extends WorkflowBlueprint<string>
 */
class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    public function actor(): \Closure
    {
        return fn() => null;
    }

    public function authorization(): null|string|callable
    {
        return null;
    }

    public function states(): array
    {
        return [
            State::make(Enum::new),
            State::make(Enum::review)->attribute('height', 100),
            Enum::published,
            State::make(Enum::correction)->withContext([
                'urgency' => 'integer'
            ]),
            Enum::unreacheable,
            Enum::chargeable,
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
                ->withContext([
                    'comment' => 'required'
                ])
                ->saving(function (Article $model, Context $context) {
                    $model->body = $context->data()['comment'];
                }),

            Transition::make(Enum::correction, Enum::review),

            Transition::make(Enum::new, Enum::chargeable)
                ->chargeable(Charge::make(
                    function (Article $model) {
                        if (! $model->votes) {
                            $model->votes = new Collection();
                        }

                        return $model->votes?->count() / 3;
                    },
                    fn(Article $model) => $model->votes->add('voice')
                ))
        ];
    }
}
