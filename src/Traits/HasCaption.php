<?php

namespace Codewiser\Workflow\Traits;

trait HasCaption
{
    protected mixed $caption = null;

    /**
     * Set caption.
     *
     * @param string|callable(\Illuminate\Database\Eloquent\Model): string $caption
     */
    public function as(string|callable $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    protected function resolveCaption(\Illuminate\Database\Eloquent\Model $model): ?string
    {
        if (is_callable($this->caption)) {
            return call_user_func($this->caption, $model);
        }

        if (is_string($this->caption)) {
            return $this->caption;
        }

        return null;
    }

    /**
     * Get the caption value.
     */
    abstract public function caption(): string;
}
