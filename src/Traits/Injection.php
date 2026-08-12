<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\StateMachine;
use Illuminate\Support\Collection;

/**
 * @mixin Collection
 */
trait Injection
{
    public function injectWith(StateMachine $engine): static
    {
        return $this->each(
            fn(Injectable $item) => $item->inject($engine)
        );
    }
}
