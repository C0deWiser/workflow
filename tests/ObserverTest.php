<?php

namespace Tests;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Example\FakedDispatcher;
use Codewiser\Workflow\Example\FakedFactory;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\StateMachineResolver;
use Codewiser\Workflow\WorkflowObserver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class ObserverTest extends TestCase
{
    public function testBasics()
    {
        $post = new Article();
        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory(), new StateMachineResolver());

        $this->assertNull($post->state, 'State is not initialized');

        // Implicit init (using observer)
        $this->assertTrue($observer->creating($post));
        $this->assertEquals(Enum::new, $post->state,
            'State value was initialized on creating event'
        );
    }

    public function testUserdata()
    {
        $wasCalled = ['creating' => false, 'created' => false, 'updating' => false, 'updated' => false];
        $dispatcher = new FakedDispatcher();
        $observer = new WorkflowObserver($dispatcher, new FakedFactory(), new StateMachineResolver());

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

        $this->assertArrayHasKey('context', $data);
        $this->assertArrayHasKey('comment', $data['context']['rules']);
        $this->assertArrayHasKey('urgency', $data['context']['rules']); // Inherited from state

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

    public function testValidatedUserdataReachesSavingCallback()
    {
        $seen = null;
        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory(), new StateMachineResolver());

        // Init with full userdata: 'comment' is declared in rules, 'file' is not
        $post = new Article();
        $post->state()->init(['comment' => 'optional', 'file' => 'stored-file']);

        $state = $post->state()->getStateListing()->initial();
        $state->saving(function (Model $model, Context $context) use (&$seen) {
            $seen = $context->data()->all();
        });
        $observer->creating($post);

        // Only the validated subset reaches the callback
        $this->assertEquals(['comment' => 'optional'], $seen);
    }

    public function testSavingCallbackHaltsInitialization()
    {
        $post = new Article();
        $post->state()->init();

        $initial = $post->state()->getStateListing()->initial();
        $initial->saving(fn() => false);

        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory(), new StateMachineResolver());

        $this->assertFalse($observer->creating($post));
    }

    public function testTransitRecoverable()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory(), new StateMachineResolver());

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

        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory(), new StateMachineResolver());

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

        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory(), new StateMachineResolver());

        $post->state = Enum::unreacheable;

        // Observer prevents changing state to unknown value
        $this->expectException(ItemNotFoundException::class);
        $observer->updating($post);
    }
}