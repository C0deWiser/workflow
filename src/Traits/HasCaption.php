<?php

namespace Codewiser\Workflow\Traits;

use Closure;
use Illuminate\Database\Eloquent\Model;

trait HasCaption
{
    protected $caption = null;

    /**
     * Set State caption.
     *
     * @param  string|callable(Model): string  $caption
     *
     * @return $this
     */
    public function as($caption): self
    {
        $this->caption = $caption;

        return $this;
    }

    protected function resolveCaption(Model $model): ?string
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
     * Get the caption of the State.
     *
     * @internal
     */
    abstract public function caption(): string;
}
