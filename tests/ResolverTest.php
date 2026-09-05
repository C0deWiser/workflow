<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Order;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\StateMachineResolver;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    public function testStateMachineCollector()
    {
        $machines = (new StateMachineResolver())->collect(new Article());

        $this->assertCount(1, $machines);
        $this->assertEquals('state', $machines->first()->attribute);
    }

    public function testStateMachineRestoreByDeclaredAttribute()
    {
        $order = new Order();

        $engine = (new StateMachineResolver())->restore($order, 'status');

        $this->assertInstanceOf(StateMachine::class, $engine);
        $this->assertEquals('status', $engine->attribute);
        $this->assertSame($order, $engine->model);
    }

    public function testStateMachineRestoreByMethodNamedAttribute()
    {
        $post = new Article();

        $engine = (new StateMachineResolver())->restore($post, 'state');

        $this->assertInstanceOf(StateMachine::class, $engine);
        $this->assertEquals('state', $engine->attribute);
        $this->assertSame($post, $engine->model);
    }

    public function testStateMachineRestoreByLegacyBlueprintClass()
    {
        $post = new Article();

        $engine = (new StateMachineResolver())->restore($post, ArticleWorkflow::class);

        $this->assertInstanceOf(StateMachine::class, $engine);
        $this->assertEquals('state', $engine->attribute);
    }

    public function testStateMachineRestoreReturnsNullForUnknownAttribute()
    {
        $this->assertNull((new StateMachineResolver())->restore(new Article(), 'unknown'));
    }

    public function testCollectReturnsEmptyForModelWithoutWorkflows()
    {
        $this->assertTrue((new StateMachineResolver())->collect(new class() extends Model {})->isEmpty());
    }

    public function testCollectRestoresEachWorkflowOfOrder()
    {
        $resolver = new StateMachineResolver();

        $this->assertCount(2, $resolver->collect(new Order()));

        $this->assertEquals('status', $resolver->restore(new Order(), 'status')->attribute);
        $this->assertEquals('notify', $resolver->restore(new Order(), 'notify')->attribute);
    }
}