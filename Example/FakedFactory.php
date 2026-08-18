<?php

namespace Codewiser\Workflow\Example;

use Illuminate\Contracts\Validation\Factory;

class FakedFactory implements Factory
{

    public function make(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        return new FakedValidator($data, $rules);
    }

    public function extend($rule, $extension, $message = null)
    {
        //
    }

    public function extendImplicit($rule, $extension, $message = null)
    {
        //
    }

    public function replacer($rule, $replacer)
    {
        //
    }
}