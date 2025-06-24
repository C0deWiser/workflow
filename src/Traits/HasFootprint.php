<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasFootprint
{
    protected mixed $footprint = null;

    /**
     * @param  callable(Model, Model):string  $callback
     */
    public function footprint(callable $callback): static
    {
        $this->footprint = $callback;

        return $this;
    }

    abstract public function chronicle(?Model $performer): ?string;
}