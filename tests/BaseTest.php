<?php

namespace Tests;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWithEnum;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\StateMachineObserver;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\TransitionCollection;
use Codewiser\Workflow\Value;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    public function testBasics()
    {
        $post = new ArticleWithEnum();

        $this->assertNull($post->state, 'State is not initialized');

        // Implicit init (using observer)
        $this->assertTrue((new StateMachineObserver)->creating($post));
        $this->assertEquals($post->state()->getStateListing()->first()->value, $post->state, 'State value was initialized on creating event');
    }

    public function testStateCollection()
    {
        $collection = StateCollection::make([Enum::new, Enum::review]);

        $this->assertCount(2, $collection);
        $this->assertNotNull($collection->one(Enum::new));
        $this->assertNotNull($collection->one(Enum::review));

        $this->expectException(ItemNotFoundException::class);
        $collection->one(Enum::correction);
    }

    public function testTransitionCollection()
    {
        $collection = TransitionCollection::make([[Enum::new, Enum::review], [Enum::review, Enum::published]]);

        $this->assertCount(1, $collection->from(Enum::new));
        $this->assertCount(1, $collection->from(Enum::review));
        $this->assertCount(0, $collection->from(Enum::published));

        $this->assertCount(1, $collection->to(Enum::review));
        $this->assertCount(1, $collection->to(Enum::published));
        $this->assertCount(0, $collection->to(Enum::new));
    }

    public function testTransitions()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::new], true);

        $this->assertCount(2, $post->state()->transitions());
    }

    public function testRules()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::review], true);

        $data = $post->state()->transitions()->first()->toArray();

        $this->assertArrayHasKey('rules', $data);
        $this->assertArrayHasKey('comment', $data['rules']);
    }

    public function testJson()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::new], true);

        $transition = $post->state()->transitionTo(Enum::review);

        $this->assertCount(1, $transition->issues());
        $this->assertEquals('Transition is disabled', $transition->issues()[0]);

        $data = $transition->toArray();

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('target', $data);
        $this->assertArrayHasKey('issues', $data);
        //$this->assertArrayHasKey('rules', $data);
    }

    public function testUniqueTransitions()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::new], true);

        $this->assertCount(1, $post->state()->transitions()->to(Enum::review));
        $this->assertCount(0, $post->state()->transitions()->to(Enum::published));
    }

    public function testRelevantTransitions()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->state()->transitions()
            ->each(function (Transition $transition) use ($post) {
                // Assert that every relevant transition starts from a current state
                $this->assertEquals($post->state, $transition->source);
            });
    }

    public function testTransitRecoverable()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->state = Enum::review;

        // Observer prevents changing state as the transition has unresolved Recoverable condition
        $this->expectException(TransitionRecoverableException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitFatal()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::review], true);

        $post->state = Enum::published;

        // Observer prevents changing state as the transition has unresolved Fatal condition
        $this->expectException(TransitionFatalException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitUnauthorized()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::correction], true);

        // Transition is not authorized
        $this->expectException(AuthorizationException::class);
        $post->state()->authorize(Enum::review);
    }

    public function testTransitUnknown()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::new], true);

        $post->state = Enum::empty;

        // Observer prevents changing state to unknown value
        $this->expectException(ItemNotFoundException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testToArray()
    {
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::new], true);

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
        $post = new ArticleWithEnum();
        $post->setRawAttributes(['state' => Enum::new], true);

        $data = $post->state()->toArray();
        $this->assertArrayHasKey('charge', $data['transitions'][1]);
        $this->assertEquals(0, $data['transitions'][1]['charge']['progress']);

        $post->state()->transit(Enum::cumulative);
        $this->assertFalse($post->state()->is(Enum::cumulative));
        $data = $post->state()->toArray();
        $this->assertEquals(1/3, $data['transitions'][1]['charge']['progress']);

        $post->state()->transit(Enum::cumulative);
        $this->assertFalse($post->state()->is(Enum::cumulative));
        $data = $post->state()->toArray();
        $this->assertEquals(2/3, $data['transitions'][1]['charge']['progress']);

        $post->state()->transit(Enum::cumulative);
        $this->assertTrue($post->state()->is(Enum::cumulative));
    }

    public function testMergeRules()
    {
        $state = new State(Enum::new);
        $state->rules([
            'comment' => 'required|string',
        ]);

        $merged = $state->mergeRules([
            'comment' => 'string|max:5',
            'source' => 'string|max:5',
        ]);

        $this->assertEquals([
            'comment' => 'required|string|max:5',
            'source' => 'string|max:5',
        ], $merged);
    }
}
