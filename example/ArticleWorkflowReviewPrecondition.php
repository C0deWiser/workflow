<?php


use Illuminate\Database\Eloquent\Model;

class ArticleWorkflowReviewPrecondition extends \Codewiser\Workflow\Precondition
{

    /**
     * Returns problem description or null if no there are no problems
     * @param Article $article
     * @return string|null
     */
    public function validate($article)
    {
        if (strlen($article->body) < 1000) {
            return 'Your Article should contain at least 1000 symbols';
        }
    }
}