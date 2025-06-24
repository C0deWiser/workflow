<?php

namespace Codewiser\Workflow\Traits;

trait HasValidationRules
{

    /**
     * Validation rules for the additional context.
     */
    protected array $rules = [];

    /**
     * Additional context.
     */
    protected \Illuminate\Config\Repository $context;

    /**
     * Get or set (and validate) additional context.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    abstract public function withContext(array $context): static;

    public function context(): \Illuminate\Config\Repository
    {
        return $this->context;
    }

    /**
     * Get attributes, that must be provided into transit() or init() method.
     */
    public function validationRules($explode = false): array
    {
        $rules = $this->rules;

        if ($explode) {
            foreach ($rules as $attribute => $rule) {
                if (is_string($rule)) {
                    $rules[$attribute] = explode('|', $rule);
                }
            }
        }

        return $rules;
    }

    /**
     * Add requirement(s) to init/transition payload.
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function mergeRules(array $rules): array
    {
        $merged = [];

        foreach ($this->rules as $attribute => $rule) {
            $rule = is_string($rule) ? explode('|', $rule) : $rule;

            if (isset($rules[$attribute])) {
                $more = is_string($rules[$attribute]) ? explode('|', $rules[$attribute]) : $rules[$attribute];

                $rule = array_unique(array_merge($rule, $more));

                unset($rules[$attribute]);
            }

            $merged[$attribute] = implode('|', $rule);
        }

        return $merged + $rules;
    }
}
