<?php

namespace Tests;

use Codewiser\Workflow\Charger;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Example\FakedDispatcher;
use Codewiser\Workflow\Example\FakedFactory;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\StateMachineResolver;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;
use Codewiser\Workflow\WorkflowObserver;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ChargerTest extends TestCase
{
    public function testChargeWithoutValidatorFactoryKeepsOnlyRuleKeys()
    {
        Container::getInstance()->forgetInstance(Factory::class);

        $seen = null;

        $charger = Charger::make(
            progress: fn() => 0,
            callback: function (Model $model, Context $context) use (&$seen) {
                $seen = $context->data()->all();
            }
        );

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $transition = Transition::make(Enum::new, Enum::review)
            ->context(['comment' => 'required'])
            ->chargeable($charger)
            ->inject($post->state());

        $transition->charger($post->state())
            ->charge($transition, ['comment' => 'yes', 'foo' => 'bar']);

        // Keys that are not declared in the rules are dropped
        $this->assertEquals(['comment' => 'yes'], $seen);
    }

    public function testChargerAllowHistoryAndChargedLevel()
    {
        $history = collect(['comment', 'vote']);
        $transition = new Transition(Enum::new, Enum::chargeable);

        $charger = Charger::make(
            progress: fn() => 0.5,
            callback: fn() => null,
        )->withHistory(fn() => $history);

        $charged = $charger->inject(new StateMachine(
            new ArticleWorkflow(),
            new Article(),
            'state'
        ));

        $this->assertEquals(0.5, $charged->chargingLevel($transition));
        $this->assertFalse($charged->isCharged($transition));
        $this->assertTrue($charged->mayCharge($transition));
        $this->assertEquals(['comment', 'vote'], $charged->getHistory($transition));

        $charged->allow(fn() => false);
        $this->assertFalse($charged->mayCharge($transition));
    }

    public function testTransitSkipsChargingWhenNotAllowed()
    {
        $charged = false;

        $charger = Charger::make(
            progress: fn() => 0,
            callback: function () use (&$charged) {
                $charged = true;
            },
        )->allow(fn() => false);

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $engine->transit(Enum::chargeable);

        // Neither charged, nor transited
        $this->assertFalse($charged);
        $this->assertEquals(Enum::new, $post->state);
    }

    public function testTransitChargesWhenFullyCharged()
    {
        $charged = false;

        $charger = Charger::make(
            progress: fn() => 1,
            callback: function () use (&$charged) {
                $charged = true;
            },
        );

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $engine->transit(Enum::chargeable);

        $this->assertTrue($charged);
        $this->assertEquals(Enum::chargeable, $post->state);
    }

    public function testPrematureReturnsTrueMakesIsChargedReturnTrue()
    {
        $charger = Charger::make(
            progress: fn() => 0.3,
            callback: fn() => null,
        )->premature(fn() => true);

        $charged = $charger->inject(new StateMachine(
            new ArticleWorkflow(),
            new Article(),
            'state'
        ));

        $transition = new Transition(Enum::new, Enum::chargeable);

        $this->assertTrue($charged->isCharged($transition));
        $this->assertEquals(0.3, $charged->chargingLevel($transition));
    }

    public function testPrematureReturnsFalseFallsBackToChargingLevel()
    {
        $charger = Charger::make(
            progress: fn() => 0.5,
            callback: fn() => null,
        )->premature(fn() => false);

        $charged = $charger->inject(new StateMachine(
            new ArticleWorkflow(),
            new Article(),
            'state'
        ));

        $transition = new Transition(Enum::new, Enum::chargeable);

        $this->assertFalse($charged->isCharged($transition));
    }

    public function testPrematureReturnsTrueAllowsTransitWithoutFullCharge()
    {
        $charged = false;

        $charger = Charger::make(
            progress: fn() => 0.2,
            callback: function () use (&$charged) {
                $charged = true;
            },
        )->premature(fn() => true);

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $engine->transit(Enum::chargeable);

        $this->assertTrue($charged);
        $this->assertEquals(Enum::chargeable, $post->state);
    }

    public function testPrematureDoesNotDispatchEventWhenTriggered()
    {
        $dispatched = false;

        $charger = Charger::make(
            progress: fn() => 0.5,
            callback: fn() => null,
        )->premature(fn() => true)->dispatchWith($this->transitionChargedDispatcher($dispatched));

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $engine->transitionTo(Enum::chargeable)->charger($engine)->charge(
            $engine->transitionTo(Enum::chargeable), []
        );

        // When premature triggers, isCharged returns true, so no TransitionCharged event
        $this->assertFalse($dispatched);
    }

    public function testWithoutPrematureNoEventWhenFullyCharged()
    {
        $dispatched = false;

        $charger = Charger::make(
            progress: fn() => 1.0,
            callback: fn() => null,
        )->dispatchWith($this->transitionChargedDispatcher($dispatched));

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $engine->transitionTo(Enum::chargeable)->charger($engine)->charge(
            $engine->transitionTo(Enum::chargeable), []
        );

        // Fully charged: no TransitionCharged event (it completed the transition)
        $this->assertFalse($dispatched);
    }

    public function testWithoutPrematureDispatchesEventWhenNotFullyCharged()
    {
        $dispatched = false;

        $charger = Charger::make(
            progress: fn() => 0.5,
            callback: fn() => null,
        )->dispatchWith($this->transitionChargedDispatcher($dispatched));

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $engine->transitionTo(Enum::chargeable)->charger($engine)->charge(
            $engine->transitionTo(Enum::chargeable), []
        );

        // Not fully charged: TransitionCharged event dispatched
        $this->assertTrue($dispatched);
    }

    public function testRedirectToAppliesRedirectedState()
    {
        $charger = Charger::make(
            progress: fn() => 1.0,
            callback: fn() => null,
        )->redirectTo(fn() => Enum::published);

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $engine->transit(Enum::chargeable);

        $this->assertEquals(Enum::published, $post->state);
        $this->assertEquals(Enum::published, $engine->redirectedTo());
    }

    public function testWithoutRedirectToTransitionKeepsItsTarget()
    {
        $charger = Charger::make(
            progress: fn() => 1.0,
            callback: fn() => null,
        );

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $engine->transit(Enum::chargeable);

        $this->assertEquals(Enum::chargeable, $post->state);
        $this->assertNull($engine->redirectedTo());
    }

    public function testRedirectToUndeclaredStateThrows()
    {
        $charger = Charger::make(
            progress: fn() => 1.0,
            callback: fn() => null,
        )->redirectTo(fn() => Enum::review);

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $this->expectException(TransitionException::class);
        $this->expectExceptionMessage('redirected to an undeclared state: review');

        $engine->transit(Enum::chargeable);
    }

    public function testRedirectToForeignEnumThrows()
    {
        $charger = Charger::make(
            progress: fn() => 1.0,
            callback: fn() => null,
        )->redirectTo(fn() => OtherEnum::foreign);

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $this->expectException(TransitionException::class);
        $this->expectExceptionMessage('while it runs on ' . Enum::class);

        $engine->transit(Enum::chargeable);
    }

    public function testRedirectToNonEnumThrows()
    {
        $charger = Charger::make(
            progress: fn() => 1.0,
            callback: fn() => null,
        )->redirectTo(fn() => 'published');

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $this->expectException(TransitionException::class);
        $this->expectExceptionMessage('not a backed enum');

        $engine->transit(Enum::chargeable);
    }

    public function testRedirectToOwnSourceThrows()
    {
        $charger = Charger::make(
            progress: fn() => 1.0,
            callback: fn() => null,
        )->redirectTo(fn() => Enum::new);

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $this->expectException(TransitionException::class);
        $this->expectExceptionMessage('redirected to its own source state: new');

        $engine->transit(Enum::chargeable);
    }

    public function testRedirectAppliesToPrematureFire()
    {
        $charger = Charger::make(
            progress: fn() => 0.2,
            callback: fn() => null,
        )->premature(fn() => true)->redirectTo(fn() => Enum::published);

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        // Charge is not full, but premature() forces the transition to fire
        $engine->transit(Enum::chargeable);

        $this->assertEquals(Enum::published, $post->state);
        $this->assertEquals(Enum::published, $engine->redirectedTo());
    }

    public function testRedirectIsNotRecordedWhenTransitionIsNotCharged()
    {
        $charger = Charger::make(
            progress: fn() => 0.2,
            callback: fn() => null,
        )->redirectTo(fn() => Enum::published);

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->chargeableBlueprint($charger), $post, 'state');

        $engine->transit(Enum::chargeable);

        // The charge is not full, the transition did not fire
        $this->assertEquals(Enum::new, $post->state);
        $this->assertNull($engine->redirectedTo());
    }

    public function testRedirectDoesNotLeakToNextTransition()
    {
        $charger = Charger::make(
            progress: fn() => 1.0,
            callback: fn() => null,
        )->redirectTo(fn() => Enum::published);

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = new StateMachine($this->redirectingBlueprint($charger), $post, 'state');

        $engine->transit(Enum::chargeable);
        $this->assertEquals(Enum::published, $post->state);

        // A plain transition, fired from the redirected state
        $post->setRawAttributes(['state' => Enum::published], true);
        $engine->transit(Enum::correction);

        $this->assertEquals(Enum::correction, $post->state);
        $this->assertNull($engine->redirectedTo());
    }

    public function testFullChargeIsRedirectedToCorrectionWhenItGotVotes()
    {
        $post = new Article();
        $engine = $this->reviewingEngine($post);

        // A single vote is a vote for one target only, ...
        $engine->transit(Enum::correction, ['comment' => 'typo']);
        $this->assertCount(1, $post->votes);
        $this->assertEquals(Enum::review, $post->state, 'The charge is not full yet');

        // ... but it charges both transitions, whichever one is voted for.
        $this->assertEquals(1 / 3, $this->chargingLevel($engine, Enum::published));
        $this->assertEquals(1 / 3, $this->chargingLevel($engine, Enum::correction));

        $engine->transit(Enum::published, ['comment' => 'looks good']);
        $this->assertEquals(Enum::review, $post->state, 'The charge is not full yet');

        // The charge is full now, and there are votes for `correction`
        $engine->transit(Enum::published, ['comment' => 'also good']);

        $this->assertCount(3, $post->votes);
        $this->assertEquals(Enum::correction, $post->state, 'Redirected to the voted target');
        $this->assertEquals(Enum::correction, $engine->redirectedTo());

        // The transition is still the one that got the last vote
        $fired = $engine->redirectedTransition();
        $this->assertEquals(Enum::review, $fired->source);
        $this->assertEquals(Enum::published, $fired->target);

        $seen = null;
        $fired->saving(function (Article $model, Context $context) use (&$seen) {
            $seen = [$context->target()->enum, $context->landedIn()->enum];
        });

        // The observer validates that very transition, and not the one
        // the model would be looked up by in the state it landed in.
        $this->assertTrue($this->observer()->updating($post));

        $this->assertEquals([Enum::published, Enum::correction], $seen,
            'The fired transition kept its own target and landed in the redirected state'
        );
    }

    public function testFullChargeIsRedirectedToPublishedWithoutCorrectionVotes()
    {
        $post = new Article();
        $engine = $this->reviewingEngine($post);

        // Three votes for `published`, none for `correction`
        $engine->transit(Enum::published, ['comment' => 'one']);
        $this->assertEquals(Enum::review, $post->state, 'The charge is not full yet');

        $engine->transit(Enum::published, ['comment' => 'two']);
        $this->assertEquals(Enum::review, $post->state, 'The charge is not full yet');

        $engine->transit(Enum::published, ['comment' => 'three']);

        $this->assertCount(3, $post->votes);
        $this->assertEquals(Enum::published, $post->state);
        $this->assertEquals(Enum::published, $engine->redirectedTo());

        $fired = $engine->redirectedTransition();
        $this->assertEquals(Enum::published, $fired->target);

        $seen = null;
        $fired->saving(function (Article $model, Context $context) use (&$seen) {
            $seen = [$context->target()->enum, $context->landedIn()->enum];
        });

        $this->assertTrue($this->observer()->updating($post));

        $this->assertEquals([Enum::published, Enum::published], $seen,
            'Without votes for `correction` the fired transition lands in `published`'
        );
    }

    public function testFullChargeOfCorrectionTransitionRedirectsToCorrection()
    {
        $post = new Article();
        $engine = $this->reviewingEngine($post);

        // Three votes for `correction` itself
        $engine->transit(Enum::correction, ['comment' => 'one']);
        $engine->transit(Enum::correction, ['comment' => 'two']);
        $engine->transit(Enum::correction, ['comment' => 'three']);

        $this->assertCount(3, $post->votes);
        $this->assertEquals(Enum::correction, $post->state);
        $this->assertEquals(Enum::correction, $engine->redirectedTo());

        $fired = $engine->redirectedTransition();
        $this->assertEquals(Enum::correction, $fired->target);

        $seen = null;
        $fired->saving(function (Article $model, Context $context) use (&$seen) {
            $seen = [$context->target()->enum, $context->landedIn()->enum];
        });

        $this->assertTrue($this->observer()->updating($post));

        $this->assertEquals([Enum::correction, Enum::correction], $seen);
    }

    /**
     * A reviewing engine, bound to a fresh article in the `review` state.
     *
     * The engine is registered as the model workflow as well, so that the
     * observer, resolving workflows through the `#[Workflow]` methods of a
     * model, gets this very engine back.
     */
    private function reviewingEngine(Article $post): StateMachine
    {
        $post->setRawAttributes(['state' => Enum::review, 'votes' => '[]'], true);

        $post->state_machines['state'] = $engine = new StateMachine($this->reviewingBlueprint(), $post, 'state');

        return $engine;
    }

    private function observer(): WorkflowObserver
    {
        return new WorkflowObserver(new FakedDispatcher(), new FakedFactory(), new StateMachineResolver());
    }

    private function chargingLevel(StateMachine $engine, \BackedEnum $target): float
    {
        $transition = $engine->transitionTo($target);

        return $transition->charger($engine)->chargingLevel($transition);
    }

    private function transitionChargedDispatcher(bool &$dispatched): \Illuminate\Events\Dispatcher
    {
        $dispatcher = new \Illuminate\Events\Dispatcher();

        $dispatcher->listen(
            \Codewiser\Workflow\Events\TransitionCharged::class,
            function () use (&$dispatched) {
                $dispatched = true;
            }
        );

        return $dispatcher;
    }

    public function testChargedEventContextCarriesNoRedirect()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new, 'votes' => '[]'], true);

        $engine = $post->state();
        $charger = $engine->transitionTo(Enum::chargeable)->charger($engine);
        $charger->redirectTo(fn() => Enum::published);

        $seen = [];
        $dispatcher = new \Illuminate\Events\Dispatcher();
        $dispatcher->listen(\Codewiser\Workflow\Events\TransitionCharged::class,
            function ($event) use (&$seen) {
                $seen[] = $event->context;
            }
        );
        $charger->dispatchWith($dispatcher);

        // The first vote leaves the charge incomplete, so a TransitionCharged
        // record is dispatched while the redirect is still unknown.
        $engine->transit(Enum::chargeable);

        $this->assertCount(1, $seen, 'Charge is incomplete, the event was dispatched');

        $this->assertNull($seen[0]->redirectedTo(),
            'A redirect is resolved only once the charge is full'
        );
        $this->assertNull($engine->redirectedTo());
        $this->assertSame($seen[0]->target(), $seen[0]->landedIn());
        $this->assertEquals(Enum::new, $post->state, 'The transition did not fire');
    }

    private function chargeableBlueprint(Charger $charger): WorkflowBlueprint
    {
        return new class($charger) extends WorkflowBlueprint
        {
            public function __construct(private Charger $charger)
            {
            }

            public function states(): array
            {
                return [Enum::new, Enum::chargeable, Enum::published];
            }

            public function transitions(): array
            {
                return [
                    Transition::make(Enum::new, Enum::chargeable)->chargeable($this->charger),
                ];
            }
        };
    }

    /**
     * Adds a plain transition out of a redirected state, to check
     * the redirect does not outlive the transition that declared it.
     */
    private function redirectingBlueprint(Charger $charger): WorkflowBlueprint
    {
        return new class($charger) extends WorkflowBlueprint
        {
            public function __construct(private Charger $charger)
            {
            }

            public function states(): array
            {
                return [Enum::new, Enum::chargeable, Enum::published, Enum::correction];
            }

            public function transitions(): array
            {
                return [
                    Transition::make(Enum::new, Enum::chargeable)->chargeable($this->charger),

                    // No transition to `Enum::published` is declared on purpose:
                    // a redirect only requires the target to be a declared state.
                    Transition::make(Enum::published, Enum::correction),
                ];
            }
        };
    }

    /**
     * Two chargeable transitions out of `review`, sharing a single charge.
     *
     * Both charges are filled by the same votes, so that a user voting for
     * `published` charges the way to `correction` as well, and the other way
     * around. Once the charge is full, the fired transition lands in
     * `correction` if anybody voted for it, and in `published` otherwise.
     */
    private function reviewingBlueprint(): WorkflowBlueprint
    {
        return new class extends WorkflowBlueprint
        {
            public function states(): array
            {
                return [Enum::review, Enum::published, Enum::correction];
            }

            public function transitions(): array
            {
                return [
                    Transition::make(Enum::review, Enum::published)
                        ->context(['comment' => 'required'])
                        ->chargeable($this->charge()),

                    Transition::make(Enum::review, Enum::correction)
                        ->context(['comment' => 'required'])
                        ->chargeable($this->charge()),
                ];
            }

            /**
             * A charge, shared by both transitions.
             */
            private function charge(): Charger
            {
                return Charger::make(
                    // A vote for any of the targets charges every transition
                    progress: fn(Article $model) => $model->votes->count() / 3,

                    // A single vote, cast for the target it was cast through
                    callback: fn(Article $model, Context $context) => $model->votes
                        ->add(['for' => $context->target()->enum->value, 'by' => $context->data()->get('comment')]),
                )

                    // A veto wins over the approvals collected so far
                    ->redirectTo(fn(Article $model) => $model->votes
                        ->contains('for', Enum::correction->value)
                        ? Enum::correction
                        : Enum::published);
            }
        };
    }
}

enum OtherEnum: string
{
    // Carries the same value as `Enum::published`: a redirect is rejected
    // for the class it runs on, not for the value it holds.
    case foreign = 'published';
}