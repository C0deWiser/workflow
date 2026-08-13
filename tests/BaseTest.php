<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\TransitionCollection;
use Codewiser\Workflow\WorkflowObserver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\ItemNotFoundException;
use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    public function testBasics()
    {
        $post = new Article();

        $this->assertNull($post->state, 'State is not initialized');

        // Implicit init (using observer)
        $this->assertTrue((new WorkflowObserver)->creating($post));
        $this->assertEquals(Enum::new, $post->state,
            'State value was initialized on creating event'
        );
    }

    public function testStateCollection()
    {
        $collection = StateCollection::make([Enum::new, Enum::review]);

        $this->assertCount(2, $collection);
        $this->assertNotNull($collection->one(Enum::new));
        $this->assertNotNull($collection->one(Enum::review));

        $this->expectException(ItemNotFoundException::class);
        $collection->one(Enum::published);
    }

    public function testTransitionCollection()
    {
        $collection = TransitionCollection::make([
            [Enum::new, Enum::review],
            [Enum::review, Enum::published]
        ]);

        $this->assertCount(1, $collection->from(Enum::new));
        $this->assertCount(1, $collection->from(Enum::review));
        $this->assertCount(0, $collection->from(Enum::published));

        $this->assertCount(1, $collection->to(Enum::review));
        $this->assertCount(1, $collection->to(Enum::published));
        $this->assertCount(0, $collection->to(Enum::new));
    }

    public function testTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $this->assertNotNull($post->state()->transitionTo(Enum::review));
        $this->assertNotNull($post->state()->transitionTo(Enum::chargeable));
        $this->assertNull($post->state()->transitionTo(Enum::published));
    }

    public function testCaption()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $transition = $post->state()->transitionTo(Enum::review);

        $post->condition = true;
        $this->assertEquals('Bad condition', $transition->caption());

        $post->condition = false;
        $this->assertEquals('Good condition', $transition->caption());
    }

    public function testAttributes()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $transition = $post->state()->transitionTo(Enum::review);

        $this->assertEquals([
            'color'  => 'red',
            'height' => 100 // Inherited from state
        ], $transition->additional());
    }

    public function testRules()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::review], true);

        $data = $post->state()->transitionTo(Enum::correction)->toArray();

        $this->assertArrayHasKey('rules', $data);
        $this->assertArrayHasKey('comment', $data['rules']);
        $this->assertArrayHasKey('urgency', $data['rules']); // Inherited from state
    }

    public function testJson()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);
        $post->condition = true;

        $data = $post->state()->getTransitionListing()->first()->toArray();

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('target', $data);
        $this->assertArrayHasKey('issues', $data);
        $this->assertEquals('Incomplete', $data['issues'][0]);
    }

    public function testForbiddenTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $this->assertCount(1, $post->state()->transitions()->to(Enum::review));
        $this->assertCount(0, $post->state()->transitions()->to(Enum::published));
    }

    public function testRelevantTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->state()->transitions()
            ->each(function (Transition $transition) use ($post) {
                // Assert that every relevant transition starts from current state
                $this->assertEquals($post->state, $transition->source);
            });
    }

    public function testTransitRecoverable()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->condition = true;
        $post->state = Enum::review;

        // Observer prevents changing state as the transition has unresolved condition
        $this->expectException(TransitionException::class);
        (new WorkflowObserver)->updating($post);
    }

    public function testTransitFatal()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->state = Enum::published;

        // Observer prevents changing state as the transition is forbidden
        $this->expectException(TransitionException::class);
        (new WorkflowObserver)->updating($post);
    }

    public function testTransitUnauthorized()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::correction], true);

        // Transition is not authorized
        $this->expectException(AuthorizationException::class);
        $post->state()->authorize(Enum::review, fn() => throw new AuthorizationException);
    }

    public function testTransitUnknown()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->state = Enum::unreacheable;

        // Observer prevents changing state to unknown value
        $this->expectException(ItemNotFoundException::class);
        (new WorkflowObserver)->updating($post);
    }

    public function testToArray()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);
        $post->condition = true;

        $data = $post->state()->toArray();

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('value', $data);
        $this->assertArrayHasKey('transitions', $data);
        $this->assertArrayHasKey('name', $data['transitions'][0]);
        $this->assertArrayHasKey('source', $data['transitions'][0]);
        $this->assertArrayHasKey('target', $data['transitions'][0]);
        $this->assertArrayHasKey('issues', $data['transitions'][0]);
        //$this->assertArrayHasKey('rules', $data['transitions'][0]);
    }

    public function testChargeable()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $data = $post->state()->toArray();
        $this->assertArrayHasKey('charge', $data['transitions'][1]);
        $this->assertEquals(0, $data['transitions'][1]['charge']['progress']);

        $post->state()->transit(Enum::chargeable);
        $data = $post->state()->toArray();
        $this->assertEquals(1 / 3, $data['transitions'][1]['charge']['progress']);

        $post->state()->transit(Enum::chargeable);
        $data = $post->state()->toArray();
        $this->assertEquals(2 / 3, $data['transitions'][1]['charge']['progress']);

        $post->state()->transit(Enum::chargeable);
        $this->assertTrue($post->state()->is(Enum::chargeable));
    }

    public function testMergeRules()
    {
        $state = new State(Enum::new);
        $state->withContext([
            'comment' => 'required|string',
        ]);

        $merged = $state->mergeRules([
            'comment' => 'string|max:5',
            'source'  => 'string|max:5',
        ]);

        $this->assertEquals([
            'comment' => 'required|string|max:5',
            'source'  => 'string|max:5',
        ], $merged);
    }

    public function testStateMachineCollector()
    {
        $machines = StateMachine::collect(new Article());

        $this->assertCount(1, $machines);
        $this->assertEquals('state', $machines->first()->attribute);
    }
}
