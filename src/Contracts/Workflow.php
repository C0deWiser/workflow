<?php

namespace Codewiser\Workflow\Contracts;

use Codewiser\Workflow\WorkflowBlueprint;

/**
 * Model with workflow.
 */
interface Workflow
{
    /**
     * Should return a blueprint for every attribute.
     *
     * @return array<string, class-string<WorkflowBlueprint>|WorkflowBlueprint>
     */
    public function blueprints(): array;
}