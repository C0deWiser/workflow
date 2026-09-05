<?php

namespace Tests;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Models\TransitionHistory;
use Codewiser\Workflow\State;
use Codewiser\Workflow\Validation;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ContextTest extends TestCase
{
    public function testContextValidationIsVariadic()
    {
        $context = new Context(
            State::make(Enum::new)->context(['comment' => 'nullable']),
            ['comment' => 'Hello']
        );

        // Mimic validator(array $data, array $rules, array $messages, array $attributes)
        $validator = fn(array $data, array $rules, array $messages = [], array $attributes = []) => [
            $data, $rules, $messages, $attributes
        ];

        [$data, $rules, $messages, $attributes] = $validator(...$context->validation());

        $this->assertEquals(['comment' => 'Hello'], $data);
        $this->assertEquals(['comment' => 'nullable'], $rules);
        $this->assertEquals([], $messages);
        $this->assertEquals([], $attributes);
    }

    public function testStoringCallbackResultOverridesStorableContext()
    {
        $state = State::make(Enum::new)->storing(function (Model $model, Context $context, TransitionHistory $log) {
            // Returned context still holds the raw file
            return $context->data()->all();
        });

        $result = $state->prepareForStoring(
            new Article(),
            new Context($state, ['comment' => 'y', 'file' => 'covers/photo.jpg']),
            new TransitionHistory()
        );

        $this->assertEquals(['comment' => 'y', 'file' => 'covers/photo.jpg'], $result);
    }

    public function testStoringCallbackNullKeepsStorableContext()
    {
        $state = State::make(Enum::new)->storing(function (Model $model, Context $context, TransitionHistory $log) {
            return null;
        });

        $result = $state->prepareForStoring(
            new Article(),
            new Context($state, ['comment' => 'y']),
            new TransitionHistory()
        );

        $this->assertEquals(['comment' => 'y'], $result);
    }

    public function testStoringCallbackReceivesContext()
    {
        $state = State::make(Enum::new)->context(['comment' => 'nullable']);

        $seen = null;
        $state->storing(function (Model $model, Context $context, TransitionHistory $log) use (&$seen) {
            $seen = $context;

            return null;
        });

        $original = new Context($state, ['comment' => 'Hello']);

        $state->prepareForStoring(new Article(), $original, new TransitionHistory());

        $this->assertSame($original, $seen);
        $this->assertSame($state, $seen->target());
        $this->assertNull($seen->transition());
    }

    public function testStateContextAccessors(): void
    {
        $state = State::make(Enum::new);

        $context = new Context($state, ['comment' => 'y']);

        $this->assertNull($context->transition());
        $this->assertNull($context->source());
        $this->assertSame($state, $context->target());
        $this->assertEquals(['comment' => 'y'], $context->data()->all());
    }

    public function testTransitionContextAccessors(): void
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $transition = $post->state()->transitionTo(Enum::review);

        $context = new Context($transition, ['comment' => 'y']);

        $this->assertSame($transition, $context->transition());
        $this->assertEquals(Enum::new, $context->source()->enum);
        $this->assertEquals(Enum::review, $context->target()->enum);
    }

    public function testMergeAttributes(): void
    {
        $base = new Validation(
            ['a' => 'required', 'b' => 'string'],
            ['a.required' => 'A is required'],
            ['a' => 'the a'],
        );

        $merged = $base->merge(
            new Validation(
                ['a' => 'nullable', 'c' => 'integer'],
                ['c.integer' => 'C should be integer'],
                ['c' => 'the c', 'a' => 'THE A'],
            )
        );

        $this->assertEquals(['a' => 'required|nullable', 'b' => 'string', 'c' => 'integer'], $merged->rules);
        $this->assertEquals(['a.required' => 'A is required', 'c.integer' => 'C should be integer'], $merged->messages);
        $this->assertEquals(['a' => 'the a', 'c' => 'the c'], $merged->attributes);
    }

    public function testMergeRules()
    {
        $base = new Validation([
            'comment' => 'required|string',
        ], [
            'comment.string' => 'Should be a string.'
        ]);

        $merged = $base->merge(
            new Validation([
                'comment' => 'string|max:5',
                'source'  => 'string|max:5',
            ], [
                'comment.string' => 'Should be a string!!!'
            ])
        );

        $this->assertEquals([
            'comment' => 'required|string|max:5',
            'source'  => 'string|max:5',
        ], $merged->rules);

        $this->assertEquals([
            'comment.string' => 'Should be a string.'
        ], $merged->messages);
    }
}