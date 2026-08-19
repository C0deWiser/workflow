<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Validation;

trait HasValidationRules
{
    /**
     * Validation rules for the additional context.
     */
    protected ?Validation $validation = null;

    /**
     * Add requirement(s) to init/transition payload.
     */
    public function context(Validation|array $rules): static
    {
        $this->validation = is_array($rules) ? new Validation($rules) : $rules;

        return $this;
    }

    /**
     * @internal
     */
    public function validation(): ?Validation
    {
        return $this->validation;
    }
}
