<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasAttributes
{
    protected array $additional = [];

    /**
     * Set any additional attribute: color, order, etc.
     *
     * @param  string  $attribute
     * @param  scalar|callable(Model):scalar  $value
     *
     * @return $this
     */
    public function set(string $attribute, $value): self
    {
        $this->additional[$attribute] = $value;

        return $this;
    }

    protected function resolveAttributes(Model $model): array
    {
        $additional = [];

        foreach ($this->additional as $attribute => $value) {
            if (is_callable($value)) {
                $additional[$attribute] = call_user_func($value, $model);
            }

            if (is_string($value)) {
                $additional[$attribute] = $value;
            }
        }

        return $additional;
    }

    /**
     * Get additional attributes.
     *
     * @internal
     */
    abstract public function additional(): array;
}
