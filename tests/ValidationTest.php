<?php

namespace Tests;

use Codewiser\Workflow\Validation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ValidationTest extends TestCase
{
    public function testFromRequestUsesFormRequestDefinition()
    {
        $form = new class extends FormRequest
        {
            public function rules(): array
            {
                return ['comment' => 'required|string'];
            }

            public function messages(): array
            {
                return ['comment.required' => 'The comment is required.'];
            }

            public function attributes(): array
            {
                return ['comment' => 'Comment'];
            }
        };

        $validation = Validation::fromRequest($form);

        $this->assertEquals(['comment' => 'required|string'], $validation->rules);
        $this->assertEquals(['comment.required' => 'The comment is required.'], $validation->messages);
        $this->assertEquals(['comment' => 'Comment'], $validation->attributes);
    }

    public function testFromRequestFallsBackToExplicitRules()
    {
        $request = new Request([], ['comment' => 'y']);

        $validation = Validation::fromRequest($request, ['comment' => 'required|string']);

        $this->assertEquals(['comment' => 'required|string'], $validation->rules);
        $this->assertEquals([], $validation->messages);
        $this->assertEquals([], $validation->attributes);
    }

    public function testFromRequestExplicitRulesOverrideFormRequest()
    {
        $form = new class extends FormRequest
        {
            public function rules(): array
            {
                return ['comment' => 'required|string'];
            }
        };

        $validation = Validation::fromRequest($form, ['comment' => 'string|max:5']);

        $this->assertEquals(['comment' => 'string|max:5'], $validation->rules);
    }

    public function testFromRequestAcceptsPlainRequest()
    {
        $request = new Request();

        $validation = Validation::fromRequest($request);

        $this->assertEquals([], $validation->rules);
        $this->assertEquals([], $validation->messages);
        $this->assertEquals([], $validation->attributes);
    }

    public function testFromRequestRejectsObjectRules()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('comment');

        $form = new class extends FormRequest
        {
            public function rules(): array
            {
                return ['comment' => ['required', Rule::exists('comments', 'id')]];
            }
        };

        Validation::fromRequest($form);
    }

    public function testFromRequestRejectsObjectRulesInExplicitRules()
    {
        $this->expectException(RuntimeException::class);

        $request = new Request();

        Validation::fromRequest($request, ['comment' => Rule::unique('comments')]);
    }

    public function testFromRequestRejectsNestedObjectRules()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('user.id');

        $request = new Request();

        Validation::fromRequest($request, ['user' => ['id' => [Rule::exists('users', 'id')]]]);
    }
}