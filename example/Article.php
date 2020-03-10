<?php


use Codewiser\Workflow\Traits\Workflow;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class Article
 *
 * @property string $body
 */
class Article extends \Illuminate\Database\Eloquent\Model
{
    use Workflow {
        booted as protected workflowBooted;
    }
    use \Codewiser\Journalism\Journalised;

    protected static function booted()
    {
        parent::booted();
        static::workflowBooted();
    }

    /**
     * Returns model workflow
     * @return ArticleWorkflow
     */
    public function workflow(): WorkflowBlueprint
    {
        return new ArticleWorkflow($this);
    }

    /**
     * @return TechnicalWorkflow
     */
    public function techWorkflow()
    {
        return new TechnicalWorkflow($this);
    }

    public function scopeTechWorkflow(Builder $query, $state)
    {
        return $query->where($this->techWorkflow()->getAttributeName(), $state);
    }
}