<?php

namespace Codewiser\Workflow\Example;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class FakeValidator implements Validator
{
    protected MessageBag $bag;
    protected array $valid = [];
    protected array $failed = [];
    protected bool $validated = false;

    public function __construct(protected array $data, protected array $rules)
    {
        $this->bag = new \Illuminate\Support\MessageBag();
    }

    public function getMessageBag(): MessageBag
    {
        return new \Illuminate\Support\MessageBag();
    }

    /**
     * @throws ValidationException
     */
    public function validate(): array
    {
        if (! $this->validated) {
            foreach ($this->rules as $attr => $rules) {

                $value = $this->data[$attr] ?? null;

                $rules = is_array($rules) ? $rules: explode('|', $rules);

                foreach ($rules as $rule) {

                    if ($rule == 'required' && ! $value) {
                        $this->bag->add($attr, "$attr is missing");
                        $this->failed[$attr] = $value;
                    } else {
                        if (isset($this->data[$attr])) {
                            $this->valid[$attr] = $value;
                        }
                    }
                }
            }

            $this->validated = true;
        }

        if ($this->failed) {
            throw new ValidationException($this);
        }

        return $this->valid;
    }

    protected function validateQuietly(): static
    {
        try {
            $this->validate();
        } catch (ValidationException) {
            // do not throw
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function validated(): array
    {
        $this->validate();

        return $this->valid;
    }

    public function fails(): bool
    {
        $this->validateQuietly();

        return (bool) $this->failed;
    }

    public function failed(): array
    {
        $this->validateQuietly();

        return $this->failed;
    }

    public function sometimes($attribute, $rules, callable $callback): static
    {
        return $this;
    }

    public function after($callback): static
    {
        return $this;
    }

    public function errors(): MessageBag
    {
        return $this->bag;
    }
}