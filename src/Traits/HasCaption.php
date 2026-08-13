<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * State or transition has a human-readable name.
 */
trait HasCaption
{
    /**
     * @var null|callable|string
     */
    protected $caption = null;

    /**
     * Set state/transition caption.
     *
     * @param  callable(Model): string|string  $caption
     */
    public function as(callable|string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    /**
     * Get the caption.
     *
     * @internal
     */
     public function caption(): ?string
     {
         if (is_callable($this->caption)) {
             return call_user_func($this->caption, $this->engine()->model);
         }

         if (is_string($this->caption)) {
             return $this->caption;
         }

         return null;
     }
}
