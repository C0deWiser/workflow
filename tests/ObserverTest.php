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

    public function testRedirectedTransitionKeepsRunningItsOwnTransition()
    {
        $wasCalled = ['saving' => false, 'saved' => false];
        $dispatcher = new FakedDispatcher();
        $observer = new WorkflowObserver($dispatcher, new FakedFactory(), new StateMachineResolver());

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new, 'votes' => '[]'], true);

        $engine = $post->state();

        // The chargeable transition is redirected to `correction`, a state
        // reachable from `new` by no declared transition at all.
        $chargeable = $engine->transitionTo(Enum::chargeable);
        $chargeable->charger($engine)->redirectTo(fn() => Enum::correction);

        $chargeable->saving(function () use (&$wasCalled) {
            $wasCalled['saving'] = true;
        });
        $chargeable->saved(function () use (&$wasCalled) {
            $wasCalled['saved'] = true;
        });

        // Three votes fill the charge, the third one fires the transition
        $engine->transit(Enum::chargeable);
        $engine->transit(Enum::chargeable);
        $engine->transit(Enum::chargeable);

        $this->assertEquals(Enum::correction, $post->state, 'Model landed in the redirected state');
        $this->assertEquals(Enum::correction, $engine->redirectedTo());

        // The observer keeps the chargeable transition running,
        // not the one the model would be looked up by.
        $this->assertTrue($observer->updating($post));

        $post->syncChanges();
        $observer->updated($post);

        $this->assertEquals(['saving' => true, 'saved' => true], $wasCalled,
            'The chargeable transition ran its own callbacks'
        );

        $transited = $dispatcher->dispatched[0];
        $this->assertInstanceOf(ModelTransited::class, $transited);
        $this->assertEquals(Enum::chargeable, $transited->context->target()->enum,
            'The running transition still reports its own target'
        );
    }

    public function testRedirectedTransitionStillValidatesItsOwnContext()
    {
        $observer = new WorkflowObserver(new FakedDispatcher(), new FakedFactory(), new StateMachineResolver());

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new, 'votes' => '[]'], true);

        $engine = $post->state();

        // `new => correction` requires a comment
        $engine->getTransitionListing()
            ->from(Enum::new)
            ->to(Enum::correction);

        $chargeable = $engine->transitionTo(Enum::chargeable);
        $chargeable->charger($engine)->redirectTo(fn() => Enum::correction);

        $engine->transit(Enum::chargeable);
        $engine->transit(Enum::chargeable);
        $engine->transit(Enum::chargeable, ['comment' => 'a comment']);

        $this->assertEquals(Enum::correction, $post->state);

        // The chargeable transition declares `comment` as nullable, so it passes.
        // Validation belongs to the running transition, not to the redirected state.
        $this->assertTrue($observer->updating($post));
    }

    public function testRedirectedTransitionRunsTheStateItLandedIn()
    {
        $wasCalled = [];
        $dispatcher = new FakedDispatcher();
        $observer = new WorkflowObserver($dispatcher, new FakedFactory(), new StateMachineResolver());

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new, 'votes' => '[]'], true);

        $engine = $post->state();

        $chargeable = $engine->transitionTo(Enum::chargeable);
        $chargeable->charger($engine)->redirectTo(fn() => Enum::correction);

        // The state the transition declares, and the state it lands in.
        $engine->getStateListing()->one(Enum::chargeable)->saving(function () use (&$wasCalled) {
            $wasCalled[] = 'chargeable saving';
        });
        $engine->getStateListing()->one(Enum::chargeable)->saved(function () use (&$wasCalled) {
            $wasCalled[] = 'chargeable saved';
        });
        $engine->getStateListing()->one(Enum::correction)->saving(function () use (&$wasCalled) {
            $wasCalled[] = 'correction saving';
        });
        $engine->getStateListing()->one(Enum::correction)->saved(function () use (&$wasCalled) {
            $wasCalled[] = 'correction saved';
        });

        $engine->transit(Enum::chargeable);
        $engine->transit(Enum::chargeable);
        $engine->transit(Enum::chargeable);

        $this->assertEquals(Enum::correction, $post->state);

        $this->assertTrue($observer->updating($post));

        $post->syncChanges();
        $observer->updated($post);

        $this->assertEquals(['correction saving', 'correction saved'], $wasCalled,
            'The state the model landed in ran its own callbacks, not the declared target'
        );
    }

    public function testTransitedEventContextExposesTheRedirect()
    {
        $dispatcher = new FakedDispatcher();
        $observer = new WorkflowObserver($dispatcher, new FakedFactory(), new StateMachineResolver());

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new, 'votes' => '[]'], true);

        $engine = $post->state();

        $chargeable = $engine->transitionTo(Enum::chargeable);
        $chargeable->charger($engine)->redirectTo(fn() => Enum::correction);

        $engine->transit(Enum::chargeable);
        $engine->transit(Enum::chargeable);
        $engine->transit(Enum::chargeable);

        $this->assertTrue($observer->updating($post));

        $post->syncChanges();
        $observer->updated($post);

        $context = $dispatcher->dispatched[0]->context;

        $this->assertEquals(Enum::chargeable, $context->target()->enum,
            'The running transition still reports its own target'
        );
        $this->assertEquals(Enum::correction, $context->redirectedTo()->enum);
        $this->assertEquals(Enum::correction, $context->landedIn()->enum);
    }
}