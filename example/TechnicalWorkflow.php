<?php


class TechnicalWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{

    const ATTRIBUTE = 'tech_workflow';

    /**
     * Array of available Model Workflow steps. First one is initial
     * @return array
     * @example [new, review, published, correcting]
     */
    protected function states(): array
    {
        return ['new', 'done'];
    }

    /**
     * Array of allowed transitions between states
     * @return \Codewiser\Workflow\Transition[]
     * @example [[new, review], [review, published], [review, correcting], [correcting, review]]
     */
    protected function transitions(): array
    {
        return [
            new \Codewiser\Workflow\Transition('new', 'done')
        ];
    }
}