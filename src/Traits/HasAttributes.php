<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasAttributes
{
    protected array $additional = [];

    /**
     * Set any additional attribute: color, order, etc.
     *
     * @param  callable(Model):scalar|scalar  $value
     */
    public function set(string $attribute, callable|float|bool|int|string $value): static
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

            if (is_scalar($value)) {
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
