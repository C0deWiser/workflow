<?php

namespace Tests;

use App\Post;
use Codewiser\Workflow\StateMachineObserver;
use Codewiser\Workflow\Transition;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
        $this->assertEquals($post->workflow()->initial(), $post->state, 'State value was initialized on creating event');
    }

    public function testTransitions()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $this->assertCount(6, $post->workflow()->transitions());
    }

    public function testRelevantTransitions()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        // Transition one-three has Fatal condition and will be rejected
        $this->assertCount(2, $post->workflow()->relevant());

        $post->workflow()->relevant()
            ->each(function (Transition $transition) use ($post) {
                // Assert that every relevant transition starts from current state
                $this->assertEquals($post->state, $transition->source());
            });
    }

    public function testTransitRecoverable()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = 'two';

        // Observer prevents changing state as the transition has unresolved Recoverable condition
        $this->assertFalse((new StateMachineObserver)->updating($post));
    }

    public function testTransitUnknown()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = Str::random();

        // Observer prevents changing state to unknown value
        $this->assertFalse((new StateMachineObserver)->updating($post));
    }

    public function testTransit()
    {
        $post = new Post();
        $post->setRawAttributes(['state' => 'one'], true);

        $post->state = 'four';

        // Observer allows to change the state
        $this->assertTrue((new StateMachineObserver)->updating($post));
    }
}
