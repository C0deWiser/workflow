<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Context;
use Illuminate\Database\Eloquent\Model;

/**
 * State or transition has some additional attributes.
 */
trait HasAttributes
{
    /**
     * @var array|callable(Model, Context): scalar
     */
    protected $attributes = [];

    /**
     * Set any additional attribute: color, order, etc.
     *
     * @param  callable(Model, Context): scalar|scalar  $value
     */
    public function attribute(string $attribute, callable|float|bool|int|string $value): static
    {
        $this->attributes[$attribute] = $value;

        return $this;
    }

    /**
     * @param  array<string, scalar> | callable(Model, Context): array<int, scalar>  $attributes
     */
    public function attributes(array|callable $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Get additional attributes.
     *
     * @return array<string, scalar>
     * @internal
     */
    public function additional(): array
    {
        $additional = [];

        $context = new Context($this);

        if (is_callable($this->attributes)) {

            $additional = call_user_func($this->attributes, $this->engine()->model, $context);

        } elseif (is_array($this->attributes)) {

            foreach ($this->attributes as $attribute => $value) {
                if (is_callable($value)) {
                    $additional[$attribute] = call_user_func($value, $this->engine()->model, $context);
                }

                if (is_scalar($value)) {
                    $additional[$attribute] = $value;
                }
            }
        }

        return $additional;
    }
}
