<?php

namespace Codewiser\Workflow;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use RuntimeException;

class Validation implements Arrayable
{
    public static function rules(array $rules): static
    {
        return new static($rules);
    }

    /**
     * Create a Validation instance from a request.
     *
     * Rules, messages and attributes are taken from the request when they
     * are defined (e.g. by a `rules()`, `messages()` and `attributes()`
     * methods of a FormRequest). Explicit rules are used instead when given.
     *
     * Objects are not allowed in rules; a rule defined as an object instance
     * (e.g. `Rule::exists()`) is rejected with a `RuntimeException`.
     *
     * @param  Request  $request
     * @param  array<string, string|array>|null  $rules  Validation rules to use instead of the request ones.
     *
     * @throws \RuntimeException When rules contain object instances.
     */
    public static function fromRequest(Request $request, ?array $rules = null): static
    {
        $rules = $rules ?? (method_exists($request, 'rules') ? $request->rules() : []);

        self::assertNoObjects($rules);

        $instance = new static($rules);

        if (method_exists($request, 'messages')) {
            $instance->messages($request->messages());
        }

        if (method_exists($request, 'attributes')) {
            $instance->attributes($request->attributes());
        }

        return $instance;
    }

    /**
     * Reject object instances in validation rules.
     *
     * @param  array<string, string|array>  $rules
     * @param  string  $attribute  Current attribute name (for diagnostics).
     *
     * @throws \RuntimeException
     */
    protected static function assertNoObjects(array $rules, string $attribute = ''): void
    {
        foreach ($rules as $key => $value) {

            $current = $attribute;

            if (is_string($key)) {
                $current = $current ? "$current.$key" : $key;
            }

            if (is_object($value)) {
                throw new RuntimeException(sprintf(
                    'Validation rules for attribute "%s" contain an object (%s), which is not allowed here.',
                    $current,
                    get_class($value)
                ));
            }

            if (is_array($value)) {
                self::assertNoObjects($value, $current);
            }
        }
    }

    /**
     * @param  array<string, string|array>  $rules  Validation rules.
     * @param  array<string, string>  $messages  Error messages.
     * @param  array<string, string>  $attributes  Attribute values.
     */
    public function __construct(
        public array $rules,
        public array $messages = [],
        public array $attributes = []
    ) {
        //
    }

    public function messages(array $messages): static
    {
        $this->messages = $messages;

        return $this;
    }

    public function attributes(array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'rules'      => $this->rules,
            'messages'   => $this->messages,
            'attributes' => $this->attributes,
        ];
    }

    /**
     * @internal
     */
    public function merge(self $their): static
    {
        $newRules = [];
        $theirRules = $their->rules;

        foreach ($this->rules as $attribute => $rules) {

            $rules = is_string($rules) ? explode('|', $rules) : $rules;

            if (isset($theirRules[$attribute])) {
                $more = is_string($theirRules[$attribute])
                    ? explode('|', $theirRules[$attribute])
                    : $theirRules[$attribute];

                $rules = array_unique(array_merge($rules, $more));

                unset($theirRules[$attribute]);
            }

            $newRules[$attribute] = implode('|', $rules);
        }

        return new static(
            $newRules + $theirRules,
            $this->messages + $their->messages,
            $this->attributes + $their->attributes
        );
    }
}