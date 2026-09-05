<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Example\FakedFactory;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\ItemNotFoundException;
use PHPUnit\Framework\TestCase;

class StateMachineTest extends TestCase
{
    public function testTransitSetsStateAndUserdata()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::review], true);

        $post->state()->transit(Enum::published, ['comment' => 'ok']);

        $this->assertEquals(Enum::published, $post->state);
        $this->assertEquals(['comment' => 'ok'], $post->state()->userdata());
    }

    public function testTransitThrowsWhenTargetIsUnknown()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $this->expectException(ItemNotFoundException::class);
        $post->state()->transit(Enum::unreacheable);
    }

    public function testUserdataIsKeptPerAttribute()
    {
        $engineA = (new Article())->state();
        $engineA->keepUserdata(['a' => 1]);

        $engineB = (new Article())->state();
        $this->assertEquals(['a' => 1], $engineB->userdata());

        $engineB->keepUserdata(['b' => 2]);
        $this->assertEquals(['b' => 2], $engineA->userdata());
    }

    public function testInitForcesStateAndKeepsUserdata()
    {
        $post = new Article();

        $post->state()->init(['comment' => 'x'], Enum::review);

        $this->assertEquals(Enum::review, $post->state);
        $this->assertEquals(['comment' => 'x'], $post->state()->userdata());
    }

    public function testInitDoesNotForceStateByDefault()
    {
        $post = new Article();

        $post->state()->init(['comment' => 'x']);

        $this->assertNull($post->state);
        $this->assertEquals(['comment' => 'x'], $post->state()->userdata());
    }

    public function testTransitionsEmptyWithoutState()
    {
        $this->assertTrue((new Article())->state()->transitions()->isEmpty());
    }

    public function testIsAndIsNot()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $engine = $post->state();

        $this->assertTrue($engine->is(Enum::new));
        $this->assertFalse($engine->is(Enum::review));
        $this->assertTrue($engine->isNot(Enum::review));
        $this->assertFalse($engine->isNot(Enum::new));

        $this->assertTrue($engine->state()->is(Enum::new));
        $this->assertTrue($engine->state()->isNot(Enum::review));
    }

    public function testToArrayListsOnlyAuthorizedTransitions()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $data = $post->state()->toArray();

        $targets = array_column($data['transitions'], 'target');

        // Forbidden (published) and unauthorized (prohibited) transitions are skipped
        $this->assertEquals([Enum::review->value, Enum::chargeable->value], $targets);
    }

    public function testTransitionFire()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::review], true);

        $post->state()->transitionTo(Enum::published)->fire();

        $this->assertEquals(Enum::published, $post->state);
    }

    public function testValidateWithOverridesContainerFactory()
    {
        Container::getInstance()->forgetInstance(Factory::class);

        $engine = new StateMachine(new ArticleWorkflow(), new Article(), 'state');

        $this->assertNull($engine->validators());

        $factory = new FakedFactory();
        $engine->validateWith($factory);

        $this->assertSame($factory, $engine->validators());
    }

    public function testAuthorizeUsesBlueprintAuthorization()
    {
        $blueprint = new class() extends WorkflowBlueprint
        {
            public function states(): array
            {
                return [Enum::new, Enum::review];
            }

            public function transitions(): array
            {
                return [[Enum::new, Enum::review]];
            }

            public function authorization(): ?callable
            {
                return fn() => false;
            }
        };

        $engine = new StateMachine($blueprint, new Article(), 'state');

        $this->expectException(AuthorizationException::class);
        $engine->getTransitionListing()->to(Enum::review)->sole()->authorize();
    }

    public function testTransitionsHideDeadEndTargetStates()
    {
        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $blueprint = new class() extends WorkflowBlueprint
        {
            public function states(): array
            {
                return [
                    Enum::new,
                    State::make(Enum::review)->unless(fn(Article $model) => $model->condition),
                ];
            }

            public function transitions(): array
            {
                return [[Enum::new, Enum::review]];
            }
        };

        $engine = new StateMachine($blueprint, $post, 'state');

        $this->assertCount(1, $engine->state()->transitions()->to(Enum::review));

        $post->condition = true;

        $this->assertTrue($engine->state()->transitions()->to(Enum::review)->isEmpty());

        $this->expectException(ItemNotFoundException::class);
        $engine->transit(Enum::review);
    }
}