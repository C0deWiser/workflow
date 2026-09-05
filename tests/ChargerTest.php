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