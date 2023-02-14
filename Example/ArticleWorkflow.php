<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;

class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
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
                ->withThreshold(
                    fn (Article $model) => count($model->voices) < 3,
                    fn (Article $model) => !in_array(auth()->id(), $model->voices),
                    function (Article $model) {
                        $model->voices = array_merge($model->voices, [auth()->id()]);
                        $model->save();
                    }
                )
        ];
    }

    public function authorize($model): bool
    {
        return true;
    }
}
