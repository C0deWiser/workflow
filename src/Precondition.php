<?php

namespace Codewiser\Workflow;

use Illuminate\Database\Eloquent\Model;

/**
 * Precondition may prevent transition to be performed
 * @package Codewiser\Workflow
 */
abstract class Precondition
{
    /**
     * Return problem description or null if no there are no problems
     * @param Model $model model instance
     * @param string $attribute model workflow column (to identify exact workflow in model)
     * @return string|null
     */
    abstract public function validate($model, $attribute);
}