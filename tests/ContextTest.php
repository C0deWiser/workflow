<?php

namespace Tests;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Models\TransitionHistory;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\Validation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
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

    public function testFilesAreFilteredOutOfStorableContext()
    {
        $file = UploadedFile::fake()->create('attachment.txt', 0);

        $context = new Context(State::make(Enum::new), [
            'file'     => $file,
            'nested'   => ['file' => $file, 'comment' => 'x'],
            'comment'  => 'y',
        ]);

        $this->assertEquals([
            'nested'  => ['comment' => 'x'],
            'comment' => 'y',
        ], $context->storable());
    }

    public function testStorableFilterIsAppliedByPrepareForStoring()
    {
        $file = UploadedFile::fake()->create('x.txt', 0);

        $state = State::make(Enum::new)->storing(function (Model $model, Context $context, TransitionHistory $log) {
            // Returned context still holds the raw file
            return $context->data()->all();
        });

        $stored = (new Context($state, ['comment' => 'y', 'file' => $file]))
            ->prepareForStoring(new Article(), new TransitionHistory());

        // Only comment survives the filter
        $this->assertEquals(['comment' => 'y'], $stored);
    }

    public function testStoringCallbacksReturnContext()
    {
        $seen = [];

        $state = State::make(Enum::new)->storing(function (Model $model, Context $context, TransitionHistory $log) use (&$seen) {
            $seen[] = 'state';

            return array_merge($context->data()->all(), ['file' => 'covers/photo.jpg']);
        });

        // A transition and its target state both process the context
        $transition = Transition::make(Enum::new, Enum::review)
            ->inject(new StateMachine(new ArticleWorkflow(), new Article(), 'state'))
            ->storing(function (Model $model, Context $context, TransitionHistory $log) use (&$seen) {
                $seen[] = 'transition';

                return array_merge($context->data()->all(), ['transition' => 'processed']);
            });
        $transition->target()->storing(function (Model $model, Context $context, TransitionHistory $log) use (&$seen) {
            $seen[] = 'target';

            return array_merge($context->data()->all(), ['target' => 'processed']);
        });

        $post = new Article();

        // State as a contextual
        $file = UploadedFile::fake()->create('x.txt', 0);
        $context = new Context($state, ['comment' => 'y', 'file' => $file]);

        $stored = $context->prepareForStoring($post, new TransitionHistory());

        $this->assertEquals(['state'], $seen);
        $this->assertEquals(['comment' => 'y', 'file' => 'covers/photo.jpg'], $stored);
        // Original context is not mutated
        $this->assertEquals(['comment' => 'y', 'file' => $file], $context->data()->all());

        // Transition as a contextual
        $context = new Context($transition, ['comment' => 'y']);

        $stored = $context->prepareForStoring($post, new TransitionHistory());

        $this->assertEquals(['state', 'transition', 'target'], $seen);
        // Target callback receives the transition's result
        $this->assertEquals(['comment' => 'y', 'transition' => 'processed', 'target' => 'processed'], $stored);
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