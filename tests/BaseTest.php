<?php

namespace Tests;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Example\FakedDispatcher;
use Codewiser\Workflow\Example\FakedFactory;
use Codewiser\Workflow\Example\FakedValidator;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\TransitionCollection;
use Codewiser\Workflow\Validation;
use Codewiser\Workflow\WorkflowObserver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    public function testFakeValidator()
    {
        $v = new FakedValidator(['name' => 'Foo'], ['name' => 'required']);
        $this->assertFalse($v->fails());
        $this->assertEquals(['name' => 'Foo'], $v->validate());

        $v = new FakedValidator([], ['name' => 'nullable']);
        $this->assertFalse($v->fails());
        $this->assertEquals([], $v->validate());

        $v = new FakedValidator(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertEquals(['name' => ''], $v->failed());
    }

    public function testBasics()
    {
        $post = new Article();
        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory());

        $this->assertNull($post->state, 'State is not initialized');

        // Implicit init (using observer)
        $this->assertTrue($observer->creating($post));
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

    public function testUserdata()
    {
        $wasCalled = ['creating' => false, 'created' => false, 'updating' => false, 'updated' => false];
        $dispatcher = new FakedDispatcher();
        $observer = new WorkflowObserver($dispatcher, new FakedFactory());

        // Init
        $post = new Article();
        $post->state()->init(['comment' => 'optional']);
        $state = $post->state()->getStateListing()->initial();

        $state->saving(function (Model $model, Context $context) use (&$wasCalled) {
            // Userdata was passed to callback
            $this->assertEquals(['comment' => 'optional'], $context->data()->all());
            $wasCalled['creating'] = true;
        });
        $observer->creating($post);

        $state->saved(function (Model $model, Context $context) use (&$wasCalled) {
            // Userdata was passed to callback
            $this->assertEquals(['comment' => 'optional'], $context->data()->all());
            $wasCalled['created'] = true;
        });
        $observer->created($post);
        $this->assertInstanceOf(ModelInitialized::class, $dispatcher->dispatched[0]);

        // Update
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::review], true);

        $transition = $post->state()->transitionTo(Enum::correction);

        $data = $transition->toArray();

        $this->assertArrayHasKey('validation', $data);
        $this->assertArrayHasKey('comment', $data['validation']['rules']);
        $this->assertArrayHasKey('urgency', $data['validation']['rules']); // Inherited from state

        try {
            // Try without required context
            $post->state()->transit(Enum::correction);
            $observer->updating($post);
            $this->fail();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ValidationException::class, $e);
            // Reset state to continue testing
            $post->setRawAttributes(['state' => Enum::review], true);
        }

        $post->state()->transit(Enum::correction, ['comment' => 'required']);

        $transition->saving(function (Model $model, Context $context) use (&$wasCalled) {
            // Userdata was passed to callback
            $this->assertEquals(['comment' => 'required'], $context->data()->all());
            $wasCalled['updating'] = true;
        });
        $observer->updating($post);

        $post->syncChanges();

        $transition->saved(function (Model $model, Context $context) use (&$wasCalled) {
            // Userdata was passed to callback
            $this->assertEquals(['comment' => 'required'], $context->data()->all());
            $wasCalled['updated'] = true;
        });
        $observer->updated($post);
        $this->assertInstanceOf(ModelTransited::class, $dispatcher->dispatched[1]);

        $this->assertEquals(['creating' => true, 'created' => true, 'updating' => true, 'updated' => true], $wasCalled);
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

        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory());

        $post->condition = true;
        $post->state = Enum::review;

        // Observer prevents changing state as the transition has unresolved condition
        $this->expectException(TransitionException::class);
        $observer->updating($post);
    }

    public function testTransitFatal()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory());

        $post->state = Enum::published;

        // Observer prevents changing state as the transition is forbidden
        $this->expectException(TransitionException::class);
        $observer->updating($post);
    }

    public function testTransitUnauthorized()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        // Transition is not authorized
        $this->expectException(AuthorizationException::class);
        $post->state()->authorize(Enum::prohibited);
    }

    public function testTransitUnknown()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory());

        $post->state = Enum::unreacheable;

        // Observer prevents changing state to unknown value
        $this->expectException(ItemNotFoundException::class);
        $observer->updating($post);
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
        // Vote counted
        $data = $post->state()->toArray();
        $this->assertEquals(1 / 3, $data['transitions'][1]['charge']['progress']);
        // Context saved
        $this->assertCount(1, $post->votes);
        $this->assertEquals([], $post->votes[0]);

        $post->state()->transit(Enum::chargeable, ['comment' => 'one']);
        // Vote counted
        $data = $post->state()->toArray();
        $this->assertEquals(2 / 3, $data['transitions'][1]['charge']['progress']);
        // Context saved
        $this->assertCount(2, $post->votes);
        $this->assertEquals(['comment' => 'one'], $post->votes[1]);

        $post->state()->transit(Enum::chargeable, ['comment' => 'two', 'foo' => 'bar']);
        // Transition completed
        $this->assertTrue($post->state()->is(Enum::chargeable));
        // Context saved
        $this->assertCount(3, $post->votes);
        $this->assertEquals(['comment' => 'two'], $post->votes[2]);
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

    public function testStateMachineCollector()
    {
        $machines = StateMachine::collect(new Article());

        $this->assertCount(1, $machines);
        $this->assertEquals('state', $machines->first()->attribute);
    }
}
