<?php

namespace App;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;

class Blueprint extends \Codewiser\Workflow\WorkflowBlueprint
{
    public function states(): array
    {
        return [
            State::one,
            State::recoverable,
            State::fatal,
            State::callback,
            State::deny
        ];
    }

    public function transitions(): array
    {
        return [
            Transition::define(State::one, State::recoverable)
                ->before(function (Post $model) {
                    throw new TransitionRecoverableException();
                })
                ->set('color', 'red'),

            Transition::define(State::one, State::fatal)->as('Fatal transition')
                ->before(function (Post $model) {
                    throw new TransitionFatalException();
                }),

            Transition::define(State::one, State::callback)
                ->rules([
                    'comment' => 'required|string'
                ])
                ->authorizedBy([$this, 'authorize'])
                ->after(function (Post $model, array $context) {
                    $model->body = $context['comment'];
                }),

            Transition::define(State::one, State::deny)
                ->authorizedBy(function (Post $model) {
                    return false;
                }),

            Transition::define(State::callback, State::one),
            Transition::define(State::recoverable, State::one),
            Transition::define(State::fatal, State::one)
        ];
    }

    public function authorize($model): bool
    {
        return true;
    }
}
