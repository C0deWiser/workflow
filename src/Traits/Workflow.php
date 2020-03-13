<?php
namespace Codewiser\Workflow\Traits;

use Codewiser\Journalism\Journalised;
use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Database\Eloquent\Builder;

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
     * Returns model workflow
     * @param string $what attribute name or workflow class (if null, then first Workflow will be returned)
     * @return WorkflowBlueprint|null
     */
    public function workflow($what = null)
    {
        foreach ((array)$this->state_machine as $attr => $class) {
            if (!$what) {
                return new $class($this, $attr);
            }
            if ($class == $what || $attr == $what) {
                return new $class($this, $attr);
            }
        }
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