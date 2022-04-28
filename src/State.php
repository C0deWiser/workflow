<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Traits\HasAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

class State implements Arrayable
{
    use HasAttributes;

    protected string $value;
    protected ?string $caption = null;
    protected ?string $group = null;

    /**
     * State new instance.
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
     */
    public function as(string $caption): self
    {
        if ($caption)
            $this->caption = $caption;
        return $this;
    }

    /**
     * Get caption of the State.
     */
    public function caption(): ?string
    {
        return $this->caption;
    }

    /**
     * Get value of the State.
     */
    public function value(): string
    {
        return $this->value;
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value(),
            'caption' => $this->caption() ?: $this->value()
        ] + $this->additional();
    }
}
