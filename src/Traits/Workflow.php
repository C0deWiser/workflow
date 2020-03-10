<?php
namespace Codewiser\Workflow\Traits;

use Codewiser\Journalism\Journalised;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Database\Eloquent\Builder;

trait Workflow
{
    use Journalised;

    /**
     * Returns model workflow
     * @return WorkflowBlueprint
     */
    abstract public function workflow(): WorkflowBlueprint;

    public function scopeWorkflow(Builder $query, $state)
    {
        return $query->where($this->workflow()->getAttributeName(), $state);
    }
}