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

        $this->assertTrue($history->blueprint() instanceof ArticleWorkflow);

        $this->assertEquals(Enum::new, $history->source()->enum);
        $this->assertEquals(Enum::review, $history->target()->enum);

        $this->assertEquals(Enum::new, $history->transition()->source()->enum);
        $this->assertEquals(Enum::review, $history->transition()->target()->enum);
    }
}