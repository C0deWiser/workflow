<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Validation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HasValidationRules
{
    /**
     * Validation rules for the additional context.
     *
     * @var null|array|Validation|Request|class-string<Request>|callable
     */
    protected $validation = null;

    /**
     * Add requirement(s) to init/transition payload.
     *
     * @param  array|Validation|Request|class-string<Request>|callable(Model, Context): (array|Validation|Request|class-string<Request>)  $rules
     */
    public function context(array|Validation|Request|string|callable $rules): static
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
            $validation = call_user_func($validation, $this->engine()->model, new Context($this));
        }

        if ($validation instanceof Request || is_string($validation)) {
            $validation = Validation::fromRequest($validation);
        } elseif (is_array($validation)) {
            $validation = new Validation($validation);
        }

        return $validation;
    }
}
