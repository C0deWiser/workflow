<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Validation;
use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\TestCase;

class StateTest extends TestCase
{
    public function testCaptionFallsBackToEnum()
    {
        $this->assertEquals('new', State::make(Enum::new)->caption());
        $this->assertEquals('Reviewable', State::make(Enum::review)->as('Reviewable')->caption());
    }

    public function testToArrayIncludesAdditionalAttributes()
    {
        $post = new Article();
        $post->condition = true;

        $state = State::make(Enum::review)
            ->attribute('height', 100)
            ->attribute('color', fn(Article $model) => $model->condition ? 'red' : 'blue')
            ->inject(new StateMachine(new ArticleWorkflow(), $post, 'state'));

        $this->assertEquals([
            'name'   => 'review',
            'value'  => 'review',
            'height' => 100,
            'color'  => 'red',
        ], $state->toArray());
    }

    public function testInitialStateOverrides()
    {
        $collection = StateCollection::make([Enum::new, Enum::review, Enum::published]);

        $this->assertEquals(Enum::new, $collection->initial()->enum);
        $this->assertEquals(Enum::review, $collection->initial(Enum::review)->enum);
    }

    public function testContextAcceptsRequest()
    {
        $post = new Article();

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

        $state = State::make(Enum::new)
            ->context($form)
            ->inject(new StateMachine(new ArticleWorkflow(), $post, 'state'));

        $validation = $state->validation();

        $this->assertInstanceOf(Validation::class, $validation);
        $this->assertEquals(['comment' => 'required|string'], $validation->rules);
        $this->assertEquals(['comment.required' => 'The comment is required.'], $validation->messages);
        $this->assertEquals(['comment' => 'Comment'], $validation->attributes);
    }

    public function testContextAcceptsRequestClassName()
    {
        $post = new Article();

        $state = State::make(Enum::new)
            ->context(\Tests\ArticleCommentRequest::class)
            ->inject(new StateMachine(new ArticleWorkflow(), $post, 'state'));

        $validation = $state->validation();

        $this->assertInstanceOf(Validation::class, $validation);
        $this->assertEquals(['comment' => 'required|string'], $validation->rules);
    }

    public function testContextAcceptsCallableReturningArray()
    {
        $post = new Article();

        $seen = null;
        $state = State::make(Enum::review)
            ->context(function (Article $model) use (&$seen) {
                $seen = $model;

                return ['state' => 'required|in:new,review,published'];
            })
            ->inject(new StateMachine(new ArticleWorkflow(), $post, 'state'));

        $validation = $state->validation();

        $this->assertSame($post, $seen);
        $this->assertInstanceOf(Validation::class, $validation);
        $this->assertEquals(['state' => 'required|in:new,review,published'], $validation->rules);
    }

    public function testContextAcceptsCallableReturningValidation()
    {
        $post = new Article();

        $state = State::make(Enum::new)
            ->context(fn() => Validation::rules(['comment' => 'nullable']))
            ->inject(new StateMachine(new ArticleWorkflow(), $post, 'state'));

        $validation = $state->validation();

        $this->assertInstanceOf(Validation::class, $validation);
        $this->assertEquals(['comment' => 'nullable'], $validation->rules);
    }

    public function testContextAcceptsCallableReturningRequest()
    {
        $post = new Article();

        $form = new class extends FormRequest
        {
            public function rules(): array
            {
                return ['comment' => 'required|string'];
            }
        };

        $state = State::make(Enum::new)
            ->context(fn() => $form)
            ->inject(new StateMachine(new ArticleWorkflow(), $post, 'state'));

        $validation = $state->validation();

        $this->assertInstanceOf(Validation::class, $validation);
        $this->assertEquals(['comment' => 'required|string'], $validation->rules);
    }

    public function testTransitionContextAcceptsRequestAndCallable()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::review], true);

        $engine = new StateMachine(new ArticleWorkflow(), $post, 'state');

        $transition = $engine->getTransitionListing()->from(Enum::review)->to(Enum::correction)->first();

        // Callable rules are merged with the target state's context rules.
        $transition->context(fn() => ['comment' => 'required']);

        $validation = $transition->validation();

        $this->assertInstanceOf(Validation::class, $validation);
        $this->assertEquals(['comment' => 'required', 'urgency' => 'integer'], $validation->rules);

        // A request is an eligible rules source as well.
        $form = new class extends FormRequest
        {
            public function rules(): array
            {
                return ['comment' => 'required'];
            }
        };

        $transition->context($form);

        $this->assertInstanceOf(Validation::class, $transition->validation());
        $this->assertEquals(['comment' => 'required', 'urgency' => 'integer'], $transition->validation()->rules);
    }

    public function testStateListingDeduplicatesByValue()
    {
        $collection = StateCollection::make([
            State::make(Enum::new)->as('First'),
            State::make(Enum::new)->as('Second'),
            Enum::review,
        ]);

        $this->assertCount(2, $collection);
        $this->assertEquals('First', $collection->one(Enum::new)->caption());
    }
}