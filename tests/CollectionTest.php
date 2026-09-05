<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\TransitionCollection;
use Illuminate\Support\ItemNotFoundException;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
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
}