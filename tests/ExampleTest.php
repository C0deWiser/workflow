<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\State;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\StateMachineObserver;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\TransitionCollection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ArticleWorkflow::$enum = false;
    }

    public function testExample()
    {
        $this->assertTrue(true);
    }

    public function testBasics()
    {
        $post = new Article();

        $this->assertNull($post->state, 'State is not initialized');

        // Implicit init (using observer)
        $this->assertTrue((new StateMachineObserver)->creating($post));
        $this->assertEquals($post->state->engine()->initial()->value, $post->state->value, 'State value was initialized on creating event');
    }

    public function testStateCollection()
    {
        $collection = StateCollection::make(['first', 'second']);

        $this->assertNotNull($collection->one('first'));
        $this->assertNotNull($collection->one('second'));

        $this->expectException(ItemNotFoundException::class);
        $collection->one('third');
    }

    public function testEnumStateCollection()
    {
        if (ArticleWorkflow::$enum) {
            $collection = StateCollection::make(State::cases());

            $this->assertNotNull($collection->one('first'));
            $this->assertNotNull($collection->one(State::first));
            $this->assertNotNull($collection->one('second'));
            $this->assertNotNull($collection->one(State::second));

            $this->expectException(ItemNotFoundException::class);
            $collection->one('third');
        }

        $this->assertTrue(true);
    }

    public function testTransitionCollection()
    {
        $collection = TransitionCollection::make([['first', 'second'], ['second', 'first']]);

        $this->assertCount(1, $collection->from('first'));
        $this->assertCount(1, $collection->from('second'));
        $this->assertCount(0, $collection->from('third'));

        $this->assertCount(1, $collection->to('first'));
        $this->assertCount(1, $collection->to('second'));
        $this->assertCount(0, $collection->to('third'));
    }

    public function testEnumTransitionCollection()
    {
        if (ArticleWorkflow::$enum) {
            $collection = TransitionCollection::make([[State::first, State::second], [State::second, State::first]]);

            $this->assertCount(1, $collection->from(State::first));
            $this->assertCount(1, $collection->from(State::second));
            $this->assertCount(1, $collection->from('first'));
            $this->assertCount(1, $collection->from('second'));
            $this->assertCount(0, $collection->from('third'));

            $this->assertCount(1, $collection->to(State::first));
            $this->assertCount(1, $collection->to(State::second));
            $this->assertCount(1, $collection->to('first'));
            $this->assertCount(1, $collection->to('second'));
            $this->assertCount(0, $collection->to('third'));
        }

        $this->assertTrue(true);
    }

    public function testTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'first'], true);

        $this->assertCount(3, $post->state->transitions());
    }

    public function testJson()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'first'], true);

        $transition = $post->state->transitions()->first();
        $data = $transition->toArray();

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('target', $data);
        $this->assertArrayHasKey('issues', $data);
        $this->assertArrayHasKey('rules', $data);
    }

    public function testRelevantTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'first'], true);

        $post->state->transitions()
            ->each(function (Transition $transition) use ($post) {
                // Assert that every relevant transition starts from current state
                $this->assertTrue($post->state->is($transition->source));
            });
    }

    public function testTransitRecoverable()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'first'], true);

        $post->state = 'recoverable';

        // Observer prevents changing state as the transition has unresolved Recoverable condition
        $this->expectException(TransitionRecoverableException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitFatal()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'first'], true);

        $post->state = 'fatal';

        // Observer prevents changing state as the transition has unresolved Fatal condition
        $this->expectException(TransitionFatalException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitUnauthorized()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'first'], true);

        // Transition is not authorized
        $this->expectException(AuthorizationException::class);
        $post->state->authorize('deny');
    }

    public function testTransitUnknown()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'first'], true);

        $post->state = Str::random();

        // Observer prevents changing state to unknown value
        $this->expectException(ItemNotFoundException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testToArray()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'first'], true);

        $data = $post->toArray();

        $data['state']['transitions'] = $post->state->transitions()->authorized()->toArray();

        $this->assertArrayHasKey('state', $data);
        $this->assertArrayHasKey('transitions', $data['state']);
        $this->assertArrayHasKey('name', $data['state']['transitions'][0]);
        $this->assertArrayHasKey('source', $data['state']['transitions'][0]);
        $this->assertArrayHasKey('target', $data['state']['transitions'][0]);
        $this->assertArrayHasKey('issues', $data['state']['transitions'][0]);
        $this->assertArrayHasKey('rules', $data['state']['transitions'][0]);
    }
}
