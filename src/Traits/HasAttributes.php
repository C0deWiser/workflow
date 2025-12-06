<?php

namespace Codewiser\Workflow\Traits;

trait HasAttributes
{
    protected array $additional = [];

    /**
     * Set any additional attribute: color, order, etc.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     *
     * @return $this
     */
    public function set(string $attribute, $value): self
    {
        $this->additional[$attribute] = $value;

        return $this;
    }

    /**
     * Get additional attributes.
     *
     * @internal
     */
    public function additional(): array
    {
        return $this->additional;
    }
}
