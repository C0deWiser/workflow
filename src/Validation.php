<?php

namespace Codewiser\Workflow;

use Illuminate\Contracts\Support\Arrayable;

class Validation implements Arrayable
{
    public static function rules(array $rules): static
    {
        return new static($rules);
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