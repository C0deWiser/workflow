<?php


use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\Transition;

class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{

    const ATTRIBUTE = 'editorial_workflow';

    /**
     * Array of available Model Workflow steps. First one is initial
     * @return array|string[]
     * @example [new, review, published, correcting]
     */
    protected function states(): array
    {
        return ['new', 'review', 'published', 'correcting'];
    }

    /**
     * Array of allowed transitions between states
     * @return array|Transition[]
     * @example [[new, review], [review, published], [review, correcting], [correcting, review]]
     */
    protected function transitions(): array
    {
        return [
            new Transition('new', 'review', new ArticleWorkflowReviewPrecondition()),
            new Transition('review', 'published'),
            new Transition('review', 'correcting'),
            new Transition('correcting', 'review', new ArticleWorkflowReviewPrecondition())
        ];
    }

}