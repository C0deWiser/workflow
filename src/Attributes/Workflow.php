<?php

namespace Codewiser\Workflow\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Workflow
{
    /**
     * @param  null|string  $attribute  Model attribute, storing the workflow state.
     *                                  Defaults to the name of the marked method.
     */
    public function __construct(public readonly ?string $attribute = null)
    {
    }
}