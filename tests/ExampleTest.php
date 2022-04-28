<?php

namespace Tests;

use App\Post;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
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
        $post = new Post();

        $this->assertNull($post->workflow(Str::random()), 'Unknown blueprint');

        $this->assertNotNull($post->workflow(), 'Blueprint exists');
        $this->assertEquals('state', $post->workflow()->attribute());
        $this->assertNull($post->workflow()->state(), 'Initial state is not initialized');

        $this->assertEquals('next', $post->workflow('next')->attribute(), 'Resolving correct blueprint');

        // Implicit init (using observer)
        $this->assertTrue((new StateMachineObserver)->creating($post));
        $this->assertEquals((string)$post->workflow()->initial(), $post->state, 'State value was initialized on creating event');
    }

    public function testTransitions()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $this->assertCount(7, $post->workflow()->transitions());
    }

    public function testJson()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $transition = $post->workflow()->routes()->first();
        $data = $transition->toArray();

        $this->assertArrayHasKey('caption', $data);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('target', $data);
        $this->assertArrayHasKey('problems', $data);
        $this->assertArrayHasKey('requires', $data);
    }

    public function testRelevantTransitions()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        // Transition one-three has Fatal condition and will be rejected
        $this->assertCount(3, $post->workflow()->routes());

        $post->workflow()->routes()
            ->each(function (Transition $transition) use ($post) {
                // Assert that every relevant transition starts from current state
                $this->assertEquals($post->state, $transition->source());
            });
    }

    public function testTransitRecoverable()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = 'recoverable';

        // Observer prevents changing state as the transition has unresolved Recoverable condition
        $this->expectException(TransitionRecoverableException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitFatal()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = 'fatal';

        // Observer prevents changing state as the transition has unresolved Fatal condition
        $this->expectException(TransitionFatalException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitUnauthorized()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        // Transition is not authorized
        $this->expectException(AuthorizationException::class);
        $post->workflow()->authorize('deny');
    }

    public function testTransitContext()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = 'callback';
        $post->workflow()->context(['foo' => 'Is not what it wants...']);

        $this->expectException(ValidationException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitUnknown()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = Str::random();

        // Observer prevents changing state to unknown value
        $this->expectException(ItemNotFoundException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitAllowed()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->workflow()->context(['comment' => Str::random()]);
        $post->state = 'callback';

        // Observer allows to change the state
        $this->assertTrue((new StateMachineObserver)->updating($post));
    }
}
