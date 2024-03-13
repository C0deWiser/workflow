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
     *
     * @var array
     */
    protected $rules = [];

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
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }
}
