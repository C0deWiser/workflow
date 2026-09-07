<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Validation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HasValidationRules
{
    /**
     * Validation rules for the additional context.
     *
     * @var null|array|Validation|Request|callable
     */
    protected $validation = null;

    /**
     * Add requirement(s) to init/transition payload.
     *
     * @param  array|Validation|Request|callable(Model): (array|Validation|Request)  $rules
     */
    public function context(array|Validation|Request|callable $rules): static
    {
        $this->validation = $rules;

        return $this;
    }

    /**
     * @internal
     */
    public function validation(): ?Validation
    {
        $validation = $this->validation;

        if (is_callable($validation)) {
            $validation = call_user_func($validation, $this->engine()->model);
        }

        if ($validation instanceof Request) {
            $validation = Validation::fromRequest($validation);
        } elseif (is_array($validation)) {
            $validation = new Validation($validation);
        }

        return $validation;
    }
}
