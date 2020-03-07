<?php
namespace Trunow\Workflow\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Workflow
{
    /**
     * Returns model workflow
     * @return \Codewiser\Workflow\Workflow
     */
    abstract public function workflow(): \Codewiser\Workflow\Workflow;

    public function scopeWorkflow(Builder $query, $state, \Codewiser\Workflow\Workflow $workflow = null)
    {
        return $query->where($workflow ? $workflow->getAttribute() : $this->workflow()->getAttribute(), $state);
    }
}