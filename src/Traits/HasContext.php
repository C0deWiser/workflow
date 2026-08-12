<?php

namespace Codewiser\Workflow\Traits;

use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Validation\ValidationException;

trait HasContext
{

    /**
     * Validation rules for the additional context.
     */
    protected array $rules = [];

    /**
     * Additional context.
     */
    protected ContextRepository $context;

    /**
     * Get or set (and validate) additional context.
     *
     * @throws ValidationException
     */
    /**
     * Get or set (and validate) transition additional context.
     *
     * @throws ValidationException
     */
    public function context(array $context = null): ContextRepository
    {
        if (is_array($context)) {

            $rules = $this->validationRules();

            if ($rules) {
                $this->context = new ContextRepository(
                    validator($context, $rules)->validate()
                );
            }
        }

        return $this->context;
    }

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
