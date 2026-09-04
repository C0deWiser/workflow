<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\WorkflowBlueprint;

/**
 * Brings workflow to Eloquent model.
 */
trait HasWorkflow
{
    /**
     * @param  class-string<WorkflowBlueprint>|WorkflowBlueprint  $blueprint
     * @param  string  $attribute May be as attribute name, as __METHOD__ (when method is same-named as an attribute).
     */
    protected function workflow(string|WorkflowBlueprint $blueprint, string $attribute): StateMachine
    {
        // If attribute sent as __METHOD__

        $needle = '::';
        $sep = strpos($attribute, $needle);

        if ($sep > 1) {
            $attribute = substr($attribute, $sep + strlen($needle));
        }

        // Instantiate Blueprint object
        $blueprint = $blueprint instanceof WorkflowBlueprint ? $blueprint : app($blueprint);

        return new StateMachine($blueprint, $this, $attribute);
    }
}
