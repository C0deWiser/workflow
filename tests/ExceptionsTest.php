<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Example\FakedDispatcher;
use Codewiser\Workflow\Example\FakedFactory;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Exceptions\WorkflowException;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\StateMachineResolver;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;
use Codewiser\Workflow\WorkflowObserver;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\ItemNotFoundException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionsTest extends TestCase
{
    public function testRecoverableExceptionContract()
    {
        $previous = new RuntimeException('Wrapped');
        $exception = new TransitionRecoverableException('Article is too short', 0, $previous);

        $this->assertInstanceOf(TransitionException::class, $exception);
        $this->assertInstanceOf(WorkflowException::class, $exception);
        $this->assertEquals(422, $exception->status);
        $this->assertEquals('Article is too short', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals(['message' => 'Article is too short'], $exception->jsonSerialize());
        $this->assertEquals('{"message":"Article is too short"}', $exception->toJson());
    }

    public function testFatalExceptionContract()
    {
        $exception = new TransitionFatalException();

        $this->assertInstanceOf(TransitionException::class, $exception);
        $this->assertInstanceOf(WorkflowException::class, $exception);
        $this->assertEquals(403, $exception->status);
        $this->assertEquals('Transition is forbidden', $exception->getMessage());
        $this->assertEquals('{"message":"Transition is forbidden"}', $exception->toJson());
    }

    public function testExceptionsRenderWithTheirStatus()
    {
        $factory = new class() extends ResponseFactory
        {
            public function __construct()
            {
                //
            }
        };

        Container::getInstance()->instance(ResponseFactoryContract::class, $factory);
        Container::getInstance()->instance(ResponseFactory::class, $factory);

        try {
            $json = Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
            $plain = Request::create('/', 'GET');

            $recoverable = new TransitionRecoverableException('Article is too short');

            $response = $recoverable->render($json);
            $this->assertEquals(422, $response->getStatusCode());
            $this->assertEquals(['message' => 'Article is too short'], json_decode($response->getContent(), true));

            $response = $recoverable->render($plain);
            $this->assertEquals(422, $response->getStatusCode());
            $this->assertEquals('Article is too short', $response->getContent());

            $fatal = new TransitionFatalException();

            $response = $fatal->render($json);
            $this->assertEquals(403, $response->getStatusCode());
            $this->assertEquals(['message' => 'Transition is forbidden'], json_decode($response->getContent(), true));

            $response = $fatal->render($plain);
            $this->assertEquals(403, $response->getStatusCode());
            $this->assertEquals('Transition is forbidden', $response->getContent());
        } finally {
            Container::getInstance()->forgetInstance(ResponseFactoryContract::class);
            Container::getInstance()->forgetInstance(ResponseFactory::class);
        }
    }

    # Recoverable exceptions describe problems a user may resolve

    public function testConditionMayThrowRecoverableException()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $transition = Transition::make(Enum::new, Enum::review)
            ->condition(fn() => throw new TransitionRecoverableException('Your article should contain at least 1000 symbols.'))
            ->inject($post->state());

        $this->assertEquals(
            ['Your article should contain at least 1000 symbols.'],
            $transition->issues()
        );
    }

    public function testIssuesCollectBothThrownAndReturnedDescriptions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);
        $post->condition = false;

        $transition = Transition::make(Enum::new, Enum::review)
            ->condition(fn() => throw new TransitionRecoverableException('Your article should contain at least 1000 symbols.'))
            ->condition(fn() => 'Your article should contain at least 1 image.')
            ->condition(fn(Article $model) => $model->condition ? 'Incomplete' : null)
            ->inject($post->state());

        // A thrown problem doesn't stop the rest of conditions from being evaluated
        $this->assertEquals([
            'Your article should contain at least 1000 symbols.',
            'Your article should contain at least 1 image.',
        ], $transition->issues());
    }

    public function testRecoverableIssueKeepsTransitionRelevant()
    {
        $post = new Article();

        $engine = $this->engine($post, $this->blueprint(
            [Enum::new, Enum::review],
            [
                Transition::make(Enum::new, Enum::review)
                    ->condition(fn() => throw new TransitionRecoverableException('Body is too short')),
            ]
        ));

        // A recoverable problem doesn't make the transition forbidden
        $this->assertFalse($engine->getTransitionListing()->sole()->isForbidden());
        $this->assertNotNull($engine->state()->transitionTo(Enum::review));

        $data = $engine->toArray();

        // The transition is still listed, with its problems for the user to resolve
        $this->assertCount(1, $data['transitions']);
        $this->assertEquals(Enum::review->value, $data['transitions'][0]['target']);
        $this->assertEquals(['Body is too short'], $data['transitions'][0]['issues']);
    }

    public function testTransitionInheritsIssuesOfItsTargetState()
    {
        $post = new Article();

        $engine = $this->engine($post, $this->blueprint(
            [
                Enum::new,
                State::make(Enum::review)->condition(fn() => throw new TransitionRecoverableException('Review queue is closed')),
            ],
            [[Enum::new, Enum::review]]
        ));

        $transition = $engine->getTransitionListing()->sole();

        $this->assertEquals(['Review queue is closed'], $transition->issues());
        $this->assertFalse($transition->isForbidden());
    }

    public function testObserverRejectsTransitionWithRecoverableIssue()
    {
        $post = new Article();

        $this->engine($post, $this->blueprint(
            [Enum::new, Enum::review],
            [
                Transition::make(Enum::new, Enum::review)
                    ->condition(fn() => throw new TransitionRecoverableException('Body is too short')),
            ]
        ));

        $post->state = Enum::review;

        $this->expectException(TransitionException::class);
        $this->expectExceptionMessage('Transition doesnt meet conditions to run.');

        $this->observer()->updating($post);
    }

    public function testConditionDoesNotSwallowFatalException()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $transition = Transition::make(Enum::new, Enum::review)
            ->condition(fn() => throw new TransitionFatalException())
            ->inject($post->state());

        // A fatal exception describes a dead end, not a problem to resolve
        $this->expectException(TransitionFatalException::class);
        $transition->issues();
    }

    public function testConditionDoesNotSwallowUnexpectedException()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $transition = Transition::make(Enum::new, Enum::review)
            ->condition(fn() => throw new RuntimeException('Database is down'))
            ->inject($post->state());

        $this->expectException(RuntimeException::class);
        $transition->issues();
    }

    # Fatal exceptions describe dead ends

    public function testWhenMayThrowFatalException()
    {
        $post = new Article();

        $engine = $this->engine($post, $this->blueprint(
            [Enum::new, Enum::published],
            [
                Transition::make(Enum::new, Enum::published)
                    ->when(fn() => throw new TransitionFatalException('Only for paid orders')),
            ]
        ));

        $this->assertTrue($engine->getTransitionListing()->sole()->isForbidden());

        // The transition is not a way out anymore
        $this->assertTrue($engine->state()->transitions()->isEmpty());
        $this->assertNull($engine->state()->transitionTo(Enum::published));
    }

    public function testUnlessMayThrowFatalException()
    {
        $post = new Article();

        $engine = $this->engine($post, $this->blueprint(
            [Enum::new, Enum::published],
            [
                Transition::make(Enum::new, Enum::published)
                    ->unless(fn() => throw new TransitionFatalException('Only for paid orders')),
            ]
        ));

        $this->assertTrue($engine->getTransitionListing()->sole()->isForbidden());
        $this->assertTrue($engine->state()->transitions()->isEmpty());
    }

    public function testTargetStateFatalExceptionHidesTransition()
    {
        $post = new Article();

        $engine = $this->engine($post, $this->blueprint(
            [
                Enum::new,
                State::make(Enum::published)->when(fn() => throw new TransitionFatalException('Publishing is disabled')),
            ],
            [[Enum::new, Enum::published]]
        ));

        // Transition becomes forbidden if its target state is forbidden too
        $this->assertTrue($engine->getTransitionListing()->sole()->isForbidden());
        $this->assertTrue($engine->state()->transitions()->isEmpty());
    }

    public function testForbiddenTransitionCanNotFire()
    {
        $post = new Article();

        $engine = $this->engine($post, $this->blueprint(
            [Enum::new, Enum::published],
            [
                Transition::make(Enum::new, Enum::published)
                    ->when(fn() => throw new TransitionFatalException('Only for paid orders')),
            ]
        ));

        $this->expectException(ItemNotFoundException::class);
        $engine->transit(Enum::published);
    }

    public function testObserverRejectsForbiddenTransition()
    {
        $post = new Article();

        $this->engine($post, $this->blueprint(
            [Enum::new, Enum::published],
            [
                Transition::make(Enum::new, Enum::published)
                    ->when(fn() => throw new TransitionFatalException('Only for paid orders')),
            ]
        ));

        $post->state = Enum::published;

        $this->expectException(TransitionException::class);
        $this->expectExceptionMessage('Transition is forbidden.');

        $this->observer()->updating($post);
    }

    public function testDeadEndDoesNotSwallowUnexpectedException()
    {
        $post = new Article();

        $transition = Transition::make(Enum::new, Enum::published)
            ->when(fn() => throw new RuntimeException('Database is down'))
            ->inject($post->state());

        $this->expectException(RuntimeException::class);
        $transition->isForbidden();
    }

    public function testVoidDeadEndCallbacksDoNotForbid()
    {
        $post = new Article();

        $engine = $this->engine($post, $this->blueprint(
            [Enum::new, Enum::published],
            [
                Transition::make(Enum::new, Enum::published)
                    ->when(fn() => null)
                    ->unless(fn() => null),
            ]
        ));

        $this->assertFalse($engine->getTransitionListing()->sole()->isForbidden());
        $this->assertNotNull($engine->state()->transitionTo(Enum::published));
    }

    /**
     * Blueprint with the given states and transitions.
     */
    private function blueprint(array $states, array $transitions): WorkflowBlueprint
    {
        return new class($states, $transitions) extends WorkflowBlueprint
        {
            public function __construct(
                protected array $workflowStates,
                protected array $workflowTransitions
            ) {
                //
            }

            public function states(): array
            {
                return $this->workflowStates;
            }

            public function transitions(): array
            {
                return $this->workflowTransitions;
            }
        };
    }

    /**
     * Engine, registered as the model workflow, so the observer picks it too.
     */
    private function engine(Article $post, WorkflowBlueprint $blueprint, \BackedEnum $state = Enum::new): StateMachine
    {
        $post->setRawAttributes(['state' => $state, 'votes' => '[]'], true);

        return $post->state_machines['state'] = new StateMachine($blueprint, $post, 'state');
    }

    private function observer(): WorkflowObserver
    {
        return new WorkflowObserver(new FakedDispatcher(), new FakedFactory(), new StateMachineResolver());
    }
}
