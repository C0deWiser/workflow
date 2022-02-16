<?php

namespace Codewiser\Workflow;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

class State implements Arrayable
{
    protected string $value;
    protected ?string $caption = null;
    protected ?string $group = null;

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
     * Set group for the State.
     *
     * @param string $group
     * @return $this
     */
    public function grouped(string $group): self
    {
        if ($group)
            $this->group = $group;
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
     * Get the group of the State.
     *
     * @return string|null
     */
    public function group(): ?string
    {
        return $this->group;
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

    public function toArray(): array
    {
        return [
            'value' => $this->value(),
            'caption' => $this->caption() ?: $this->value(),
            'group' => $this->group(),
        ];
    }
}