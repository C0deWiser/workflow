<?php

namespace Codewiser\Workflow\Contracts;

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
