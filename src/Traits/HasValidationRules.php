<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Transition;
use Illuminate\Support\Collection;

trait HasValidationRules
{

    /**
     * Validation rules for the transition context.
     *
     * @var array
     */
    protected $rules = [];

    /**
     * Get attributes, that must be provided into transit() method.
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
     * Add requirement(s) to transition payload.
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }
}
