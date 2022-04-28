<?php

namespace Codewiser\Workflow;

use BackedEnum;
use Illuminate\Support\Collection;

class StateCollection extends Collection
{
    /**
     * Get the exact one state from collection.
     */
    public function one(BackedEnum $state): BackedEnum
    {
        return $this
            ->sole(function (BackedEnum $st) use ($state) {
                return $st === $state;
            });
    }
}
