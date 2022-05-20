<?php

namespace Codewiser\Workflow\Traits;

trait HasCaption
{
    protected ?string $caption = null;

    /**
     * Set State caption.
     */
    public function as(string $caption): static
    {
        if ($caption)
            $this->caption = $caption;

        return $this;
    }

    /**
     * Get caption of the State.
     */
    abstract public function caption(): string;
}
