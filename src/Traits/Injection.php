<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Support\Collection;

/**
 * @mixin Collection
 */
trait Injection
{
    public function injectWith(StateMachineEngine $engine): self
    {
        return $this->each(function (Injectable $item) use ($engine) {
            $item->inject($engine);
        });
    }
}
