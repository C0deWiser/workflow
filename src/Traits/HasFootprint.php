<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasFootprint
{
    protected $footprint = null;

    public function footprint(\Closure $callback): self
    {
        $this->footprint = $callback;

        return $this;
    }

    abstract public function chronicle(?Model $performer): ?string;
}