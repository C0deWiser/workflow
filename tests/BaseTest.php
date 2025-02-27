<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
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
        $post = new Article();

        $this->assertNull($post->state, 'State is not initialized');

        // Implicit init (using observer)
        $this->assertTrue((new StateMachineObserver)->creating($post));
        $this->assertEquals($post->state()->getStateListing()->first()->value, $post->state, 'State value was initialized on creating event');
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
        if (Value::enum_support()) {
            $collection = StateCollection::make(Enum::cases());

            $this->assertNotNull($collection->one(Enum::new));
            $this->assertNotNull($collection->one(Enum::review));
            $this->assertNotNull($collection->one(Enum::published));
            $this->assertNotNull($collection->one(Enum::correction));

            $this->expectException(ItemNotFoundException::class);
            $collection->one('third');
        } else {
            $this->markTestSkipped('php@8.1 required');
        }
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
        if (Value::enum_support()) {
            $collection = TransitionCollection::make([[Enum::new, Enum::review], [Enum::review, Enum::published]]);

            $this->assertCount(1, $collection->from(Enum::new));
            $this->assertCount(1, $collection->from(Enum::review));
            $this->assertCount(0, $collection->from(Enum::published));
            $this->assertCount(0, $collection->from('third'));

            $this->assertCount(0, $collection->to(Enum::new));
            $this->assertCount(1, $collection->to(Enum::review));
            $this->assertCount(1, $collection->to(Enum::published));
            $this->assertCount(0, $collection->to('third'));
        } else {
            $this->markTestSkipped('php@8.1 required');
        }
    }

    public function testTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'new'], true);

        $this->assertCount(2, $post->state()->transitions());
    }

    public function testRules()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'review'], true);

        $data = $post->state()->transitions()->first()->toArray();

        $this->assertArrayHasKey('rules', $data);
        $this->assertArrayHasKey('comment', $data['rules']);
    }

    public function testJson()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'new'], true);

        $data = $post->state()->getTransitionListing()->first()->toArray();

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('target', $data);
        $this->assertArrayHasKey('issues', $data);
        //$this->assertArrayHasKey('rules', $data);
    }

    public function testUniqueTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'new'], true);

        $this->assertCount(1, $post->state()->transitions()->to('review'));
        $this->assertCount(0, $post->state()->transitions()->to('published'));
    }

    public function testRelevantTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'new'], true);

        $post->state()->transitions()
            ->each(function (Transition $transition) use ($post) {
                // Assert that every relevant transition starts from current state
                $this->assertEquals($post->state, $transition->source);
            });
    }

    public function testTransitRecoverable()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'new'], true);

        $post->state = 'review';

        // Observer prevents changing state as the transition has unresolved Recoverable condition
        $this->expectException(TransitionRecoverableException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitFatal()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'review'], true);

        $post->state = 'published';

        // Observer prevents changing state as the transition has unresolved Fatal condition
        $this->expectException(TransitionFatalException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testTransitUnauthorized()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'correction'], true);

        // Transition is not authorized
        $this->expectException(AuthorizationException::class);
        $post->state()->authorize('review');
    }

    public function testTransitUnknown()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'new'], true);

        $post->state = 'empty';

        // Observer prevents changing state to unknown value
        $this->expectException(ItemNotFoundException::class);
        (new StateMachineObserver)->updating($post);
    }

    public function testToArray()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => 'new'], true);

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
        $post->setRawAttributes(['state' => 'new'], true);

        $data = $post->state()->toArray();
        $this->assertArrayHasKey('charge', $data['transitions'][1]);
        $this->assertEquals(0, $data['transitions'][1]['charge']['progress']);

        $post->state()->transit('cumulative');
        $data = $post->state()->toArray();
        $this->assertEquals(1/3, $data['transitions'][1]['charge']['progress']);

        $post->state()->transit('cumulative');
        $data = $post->state()->toArray();
        $this->assertEquals(2/3, $data['transitions'][1]['charge']['progress']);

        $post->state()->transit('cumulative');
        $this->assertTrue($post->state()->is('cumulative'));
    }

    public function testMergeRules()
    {
        $state = new State('one');
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
