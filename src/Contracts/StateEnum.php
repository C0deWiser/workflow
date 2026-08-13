<?php

namespace Codewiser\Workflow\Contracts;

interface StateEnum
{
    /**
     * Human-readable state caption.
     */
    public function caption(): string;

    /**
     * State additional attributes.
     *
     * @return array<string, scalar>
     */
    public function attributes(): array;
}
