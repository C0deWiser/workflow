<?php

namespace Tests;

use Codewiser\Workflow\Charger;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
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

    private function chargeableBlueprint(Charger $charger): WorkflowBlueprint
    {
        return new class($charger) extends WorkflowBlueprint
        {
            public function __construct(private Charger $charger)
            {
            }

            public function states(): array
            {
                return [Enum::new, Enum::chargeable];
            }

            public function transitions(): array
            {
                return [
                    Transition::make(Enum::new, Enum::chargeable)->chargeable($this->charger),
                ];
            }
        };
    }
}