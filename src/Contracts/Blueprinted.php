<?php

namespace Codewiser\Workflow\Contracts;

use Codewiser\Workflow\WorkflowBlueprint;

interface Blueprinted
{
    /**
     * Should return a blueprint for every attribute.
     *
     * @return array<string, class-string<WorkflowBlueprint>|WorkflowBlueprint>
     */
    public function blueprints(): array;
}