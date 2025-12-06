<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;
use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait HasValidationRules
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
    abstract public function context(array $context = null): ContextRepository;

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
     * Add requirement(s) to init/transition payload.
     *
     * @return $this
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
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
