<?php
namespace Codewiser\Workflow\Traits;

use Codewiser\Journalism\Journalised;
use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Trait adds Workflow to Model
 * @package Codewiser\Workflow\Traits
 */
trait Workflow
{
    use Journalised;

    /**
     * Set of Workflow this model follows
     * attribute_name => Workflow::class
     * @return array
     */
    abstract protected function stateMachine();

    /**
     * Get the model workflow
     * @param string $what attribute name or workflow class (if null, then first Workflow will be returned)
     * @return WorkflowBlueprint|null
     */
    public function workflow($what = null)
    {
        foreach ((array)$this->stateMachine() as $attr => $class) {
            if (!$what || $class == $what || $attr == $what) {
                return new $class($this, $attr);
            }
        }
    }

    /**
     * Get listing of workflow, applied to the model
     * @return Collection|WorkflowBlueprint[]
     */
    public function getWorkflowListing()
    {
        $list = collect();
        foreach ((array)$this->stateMachine() as $workflow) {
            $list->push($this->workflow($workflow));
        }
        return $list;
    }

    public function scopeWorkflow(Builder $query, $workflow, $state)
    {
        if (is_string($workflow)) {
            $workflow = $this->workflow($workflow);
        }
        if (!$workflow) {
            throw new WorkflowException("Workflow not found");
        }
        return $query->where($workflow->getAttributeName(), $state);
    }
}