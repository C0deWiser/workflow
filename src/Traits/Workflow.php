<?php
namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Trait adds Workflow to Model
 * @package Codewiser\Workflow\Traits
 */
trait Workflow
{
    /**
     * Set of Workflow this model follows
     * attribute_name => Workflow::class
     * @return array
     */
    abstract protected function workflowBlueprint();

    /**
     * Get the model workflow
     * @param string $what attribute name or workflow class (if null, then first Workflow will be returned)
     * @return StateMachineEngine|null
     */
    public function workflow($what = null)
    {
        foreach ((array)$this->workflowBlueprint() as $attr => $class) {
            if (!$what || $class == $what || $attr == $what) {
                return new StateMachineEngine(new $class(), $this, $attr);
            }
        }
        return null;
    }

    /**
     * Get listing of workflow, applied to the model
     * @return Collection|StateMachineEngine[]
     */
    public function getWorkflowListing()
    {
        $list = collect();
        foreach ((array)$this->workflowBlueprint() as $workflow) {
            $list->push($this->workflow($workflow));
        }
        return $list;
    }

    public function scopeOnlyState(Builder $query, $state, $workflow = null)
    {
        if (is_null($workflow)) {
            $workflow = $this->workflow();
        }
        if (is_string($workflow)) {
            $workflow = $this->workflow($workflow);
        }
        if (!$workflow) {
            throw new WorkflowException("Workflow not found");
        }
        return $query->where($workflow->getAttributeName(), $state);
    }
}