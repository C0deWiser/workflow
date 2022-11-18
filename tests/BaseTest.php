<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
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

class BaseTest extends TestCase
{
    public function testBasics()
    {
        $post = new Article();

        $this->assertNull($post->state, 'State is not initialized');

        // Implicit init (using observer)
        $this->assertTrue((new StateMachineObserver)->creating($post));
        $this->assertEquals($post->state()->states()->first()->state, $post->state, 'State value was initialized on creating event');
    }

    public function testStateCollection()
    {
        $collection = StateCollection::make(['first', 'second']);

        $this->assertCount(2, $collection);
        $this->assertNotNull($collection->one('first'));
        $this->assertNotNull($collection->one('second'));

        $this->expectException(ItemNotFoundException::class);
        $collection->one('third');
    }

    public function testEnumStateCollection()
    {

        $collection = StateCollection::make(Enum::cases());

        $this->assertNotNull($collection->one(Enum::new));
        $this->assertNotNull($collection->one(Enum::review));
        $this->assertNotNull($collection->one(Enum::published));
        $this->assertNotNull($collection->one(Enum::correction));

        $this->expectException(ItemNotFoundException::class);
        $collection->one('third');
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
        $collection = TransitionCollection::make([[Enum::new, Enum::review], [Enum::review, Enum::published]]);

        $this->assertCount(1, $collection->from(Enum::new));
        $this->assertCount(1, $collection->from(Enum::review));
        $this->assertCount(0, $collection->from(Enum::published));
        $this->assertCount(0, $collection->from('third'));

        $this->assertCount(0, $collection->to(Enum::new));
        $this->assertCount(1, $collection->to(Enum::review));
        $this->assertCount(1, $collection->to(Enum::published));
        $this->assertCount(0, $collection->to('third'));

    }

    public function testTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $this->assertCount(1, $post->state()->getRoutes());
    }

    public function testJson()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $data = $post->state()->transitions()->first()->toArray();

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('target', $data);
        $this->assertArrayHasKey('issues', $data);
        $this->assertArrayHasKey('rules', $data);
    }

    public function testUniqueTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $this->assertCount(1, $post->state()->getRoutes()->to(Enum::review));
        $this->assertCount(0, $post->state()->getRoutes()->to(Enum::published));
    }

    public function testRelevantTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->state()->getRoutes()
            ->each(function (Transition $transition) use ($post) {
                // Assert that every relevant transition starts from current state
                $this->assertEquals($post->state, $transition->source);
            });
    }

    public function testTransitRecoverable()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->state = Enum::review;

        // Observer prevents changing state as the transition has unresolved Recoverable condition
        $this->expectException(TransitionRecoverableException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitFatal()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::review], true);

        $post->state = Enum::published;

        // Observer prevents changing state as the transition has unresolved Fatal condition
        $this->expectException(TransitionFatalException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitUnauthorized()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::correction], true);

        // Transition is not authorized
        $this->expectException(AuthorizationException::class);
        $post->state()->authorize(Enum::review);
    }

    public function testTransitUnknown()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->state = Enum::empty;

        // Observer prevents changing state to unknown value
        $this->expectException(ItemNotFoundException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testToArray()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $data = $post->state()->toArray();

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('value', $data);
        $this->assertArrayHasKey('transitions', $data);
        $this->assertArrayHasKey('name', $data['transitions'][0]);
        $this->assertArrayHasKey('source', $data['transitions'][0]);
        $this->assertArrayHasKey('target', $data['transitions'][0]);
        $this->assertArrayHasKey('issues', $data['transitions'][0]);
        $this->assertArrayHasKey('rules', $data['transitions'][0]);
    }
}
