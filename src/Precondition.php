<?php

namespace Codewiser\Workflow;

use Illuminate\Database\Eloquent\Model;

abstract class Precondition
{
    /**
     * Returns problem description or null if no there are no problems
     * @param Model $model
     * @return string|null
     */
    abstract public function validate($model);
}