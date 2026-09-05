<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\StateMachine;
use PHPUnit\Framework\TestCase;

class StateTest extends TestCase
{
    public function testCaptionFallsBackToEnum()
    {
        $this->assertEquals('new', State::make(Enum::new)->caption());
        $this->assertEquals('Reviewable', State::make(Enum::review)->as('Reviewable')->caption());
    }

    public function testToArrayIncludesAdditionalAttributes()
    {
        $post = new Article();
        $post->condition = true;

        $state = State::make(Enum::review)
            ->attribute('height', 100)
            ->attribute('color', fn(Article $model) => $model->condition ? 'red' : 'blue')
            ->inject(new StateMachine(new ArticleWorkflow(), $post, 'state'));

        $this->assertEquals([
            'name'   => 'review',
            'value'  => 'review',
            'height' => 100,
            'color'  => 'red',
        ], $state->toArray());
    }

    public function testInitialStateOverrides()
    {
        $collection = StateCollection::make([Enum::new, Enum::review, Enum::published]);

        $this->assertEquals(Enum::new, $collection->initial()->enum);
        $this->assertEquals(Enum::review, $collection->initial(Enum::review)->enum);
    }

    public function testStateListingDeduplicatesByValue()
    {
        $collection = StateCollection::make([
            State::make(Enum::new)->as('First'),
            State::make(Enum::new)->as('Second'),
            Enum::review,
        ]);

        $this->assertCount(2, $collection);
        $this->assertEquals('First', $collection->one(Enum::new)->caption());
    }
}