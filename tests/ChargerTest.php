<?php

namespace Tests;

use Codewiser\Workflow\Charger;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;
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
}

enum OtherEnum: string
{
    // Carries the same value as `Enum::published`: a redirect is rejected
    // for the class it runs on, not for the value it holds.
    case foreign = 'published';
}