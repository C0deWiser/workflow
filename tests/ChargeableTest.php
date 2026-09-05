<?php

namespace Tests;

use Codewiser\Workflow\Charger;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Example\FakedFactory;
use Codewiser\Workflow\Transition;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class ChargeableTest extends TestCase
{
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

    public function testChargeValidatesUserdata()
    {
        $seen = null;

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $charger = Charger::make(
            progress: fn(Article $model) => 0,
            callback: function (Article $model, Context $context) use (&$seen) {
                $seen = $context->data()->all();
            }
        );

        $transition = Transition::make(Enum::new, Enum::published)
            ->context(['comment' => 'required'])
            ->chargeable($charger)
            ->inject($post->state());

        // Provide FakedValidator through the container, so the engine may resolve it
        $container = Container::getInstance();
        $container->instance(Factory::class, new FakedFactory());

        try {
            // Inject the engine into the transition (and thus into the charger)
            $charger = $transition->charger($post->state());

            // Validate data before handing it to the charge callback
            $charger->charge($transition, ['comment' => 'yes', 'foo' => 'bar']);

            // Only validated keys are passed
            $this->assertEquals(['comment' => 'yes'], $seen);

            // Invalid data throws
            $this->expectException(ValidationException::class);
            $charger->charge($transition, []);
        } finally {
            $container->forgetInstance(Factory::class);
        }
    }
}