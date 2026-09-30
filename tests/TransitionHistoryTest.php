<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Models\TransitionHistory;
use PHPUnit\Framework\TestCase;

class TransitionHistoryTest extends TestCase
{
    public function testRestoreObjects()
    {
        $history = new TransitionHistory();

        $history->source = Enum::new;
        $history->target = Enum::review;
        $history->blueprint = ArticleWorkflow::class;
        $history->transitionable = new Article();
        $history->context = ['name' => 'Foo'];

        $this->assertTrue($history->blueprint() instanceof ArticleWorkflow);

        $this->assertEquals(Enum::new, $history->source()->enum);
        $this->assertEquals(Enum::new, $history->context()->source()->enum);

        $this->assertEquals(Enum::review, $history->target()->enum);
        $this->assertEquals(Enum::review, $history->context()->target()->enum);

        $this->assertEquals(Enum::new, $history->transition()->source()->enum);
        $this->assertEquals(Enum::new, $history->context()->transition()->source()->enum);

        $this->assertEquals(Enum::review, $history->transition()->target()->enum);
        $this->assertEquals(Enum::review, $history->context()->transition()->target()->enum);

        $this->assertEquals(['name' => 'Foo'], $history->context()->data()->all());
    }

    public function testRestoredContextLandsInTheStoredTarget(): void
    {
        $history = new TransitionHistory();

        $history->source = Enum::review;
        $history->target = Enum::correction;
        $history->blueprint = ArticleWorkflow::class;
        $history->transitionable = new Article();

        // A stored record keeps the state the model landed in, and knows
        // nothing about a redirect that got it there.
        $context = $history->context();

        $this->assertNull($context->redirectedTo());
        $this->assertEquals(Enum::correction, $context->target()->enum);
        $this->assertSame($context->target(), $context->landedIn());
    }
}