<?php

namespace Codewiser\Workflow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated
 */
interface StateEnum
{
    /**
     * Human-readable state caption.
     */
    public function caption(): string;

    /**
     * State additional attributes (key->value array).
     */
    public function attributes(): array;
}
