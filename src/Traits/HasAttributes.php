<?php

namespace Codewiser\Workflow\Traits;

trait HasAttributes
{
    protected array $additional = [];

    /**
     * Set any additional attribute: color, order etc
     */
    public function set(string $attribute, mixed $value): self
    {
        $this->additional[$attribute] = $value;

        return $this;
    }

    /**
     * Get additional attributes.
     */
    public function additional(): array
    {
        return $this->additional;
    }
}
