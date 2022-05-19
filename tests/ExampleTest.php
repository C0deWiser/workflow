<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachineObserver;
use Codewiser\Workflow\Transition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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

    public function testTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        $this->assertCount(3, $post->state->transitions());
    }

    public function testJson()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        $transition = $post->state->transitions()->first();
        $data = $transition->toArray();

        $this->assertArrayHasKey('caption', $data);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('target', $data);
        $this->assertArrayHasKey('problems', $data);
        $this->assertArrayHasKey('requires', $data);
    }

    public function testRelevantTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state->transitions()
            ->each(function (Transition $transition) use ($post) {
                // Assert that every relevant transition starts from current state
                $this->assertTrue($post->state->is($transition->source()));
            });
    }

    public function testTransitRecoverable()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = 'recoverable';

        // Observer prevents changing state as the transition has unresolved Recoverable condition
        $this->expectException(TransitionRecoverableException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitFatal()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = 'fatal';

        // Observer prevents changing state as the transition has unresolved Fatal condition
        $this->expectException(TransitionFatalException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitUnauthorized()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        // Transition is not authorized
        $this->expectException(AuthorizationException::class);
        $post->state->authorize('deny');
    }

    public function testTransitContext()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = 'callback';
        $post->state->context(['foo' => 'Is not what it wants...']);

        $this->expectException(ValidationException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitUnknown()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = Str::random();

        // Observer prevents changing state to unknown value
        $this->expectException(ItemNotFoundException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitAllowed()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state->context(['comment' => Str::random()]);
        $post->state = 'callback';

        // Observer allows to change the state
        $this->assertTrue((new StateMachineObserver)->updating($post));
    }

    public function testToArray()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'one'], true);

        $data = $post->toArray();

        $data['state']['transitions'] = $post->state->transitions()->authorized()->toArray();

        $this->assertArrayHasKey('state', $data);
        $this->assertArrayHasKey('transitions', $data['state']);
        $this->assertArrayHasKey('caption', $data['state']['transitions'][0]);
        $this->assertArrayHasKey('source', $data['state']['transitions'][0]);
        $this->assertArrayHasKey('target', $data['state']['transitions'][0]);
        $this->assertArrayHasKey('problems', $data['state']['transitions'][0]);
        $this->assertArrayHasKey('requires', $data['state']['transitions'][0]);

        dump($data);
    }
}
