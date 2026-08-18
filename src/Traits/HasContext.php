<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Config\Repository as UserData;
use Illuminate\Validation\ValidationException;

trait HasContext
{
    /**
     * Validation rules for the additional context.
     */
    protected array $rules = [];

    /**
     * Add requirement(s) to init/transition payload.
     */
    public function withContext(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Get attributes, that must be provided into transit() or init() method.
     *
     * @internal
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
     * @internal
     */
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
