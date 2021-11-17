<?php

namespace Codewiser\Workflow;

use Illuminate\Support\Str;

class State
{
    protected string $value;
    protected ?string $caption = null;

    /**
     * State new instance.
     *
     * @param string $state
     * @return static
     */
    public static function define(string $state): self
    {
        return new static($state);
    }

    public function __construct(string $state)
    {
        $this->value = $state;
    }

    public function __toString()
    {
        return $this->value();
    }

    /**
     * Set State caption.
     *
     * @param string $caption
     * @return $this
     */
    public function as(string $caption): self
    {
        if ($caption)
            $this->caption = $caption;
        return $this;
    }

    /**
     * Get caption of the State.
     *
     * @return string|null
     */
    public function caption(): ?string
    {
        return $this->caption;
    }

    /**
     * Get value of the State.
     *
     * @return string
     */
    public function value(): string
    {
        return $this->value;
    }
}