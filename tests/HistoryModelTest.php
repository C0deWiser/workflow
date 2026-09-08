<?php

namespace Tests;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Events\TransitionCharged;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\CustomHistory;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Listeners\TransitionListener;
use Codewiser\Workflow\Models\TransitionHistory;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachine;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class HistoryModelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->bootEloquent();

        $capsule->getConnection()->getSchemaBuilder()->create('transition_history', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('performer');
            $table->nullableMorphs('transitionable');
            $table->string('blueprint')->nullable();
            $table->string('source')->nullable();
            $table->string('target')->nullable();
            $table->text('context')->nullable();
            $table->timestamps();
        });

        $capsule->getConnection()->getSchemaBuilder()->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('state')->nullable();
            $table->text('votes')->nullable();
            $table->timestamps();
        });
    }

    public function testCustomHistoryModelResolution()
    {
        $this->assertEquals(TransitionHistory::class, TransitionHistory::model());

        TransitionHistory::useModel(CustomHistory::class);
        $this->assertEquals(CustomHistory::class, TransitionHistory::model());

        TransitionHistory::useModel(TransitionHistory::class);
        $this->assertEquals(TransitionHistory::class, TransitionHistory::model());
    }

    public function testTransitionsRelationUsesConfiguredHistoryModel()
    {
        TransitionHistory::useModel(CustomHistory::class);

        $post = new Article();

        $this->assertInstanceOf(CustomHistory::class, $post->transitions()->getRelated());
        $this->assertInstanceOf(CustomHistory::class, $post->latest_transition()->getRelated());

        TransitionHistory::useModel(TransitionHistory::class);
    }

    public function testListenerStoresExtendedHistoryModel()
    {
        TransitionHistory::useModel(CustomHistory::class);

        // fake the auth facade (auth()->user())
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new class()
        {
            public function guard($name = null)
            {
                return $this;
            }

            public function user()
            {
                return null;
            }
        });

        $seen = null;
        $state = State::make(Enum::new)->storing(function (Model $model, Context $context, TransitionHistory $log) use (&$seen) {
            // The extended history record is in use here
            $seen = $log;
        });

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $context = new Context($state, ['comment' => 'y']);

        (new TransitionListener())->handleTransition(new ModelTransited($post->state(), $context));

        $this->assertInstanceOf(CustomHistory::class, $seen);
        $this->assertNotNull($seen->getKey());
        $this->assertSame($post, $seen->transitionable);
        $this->assertEquals(['comment' => 'y'], $seen->context);

        TransitionHistory::useModel(TransitionHistory::class);
    }

    public function testListenerStoresInitialHistoryRecord()
    {
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new class()
        {
            public function guard($name = null)
            {
                return $this;
            }

            public function user()
            {
                return null;
            }
        });

        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        $context = new Context(State::make(Enum::new), ['comment' => 'y']);

        (new TransitionListener())->handleInitialization(new ModelInitialized($post->state(), $context));

        $record = $post->transitions()->first();

        $this->assertNotNull($record);
        $this->assertNull($record->source);
        $this->assertEquals('new', $record->target);
        $this->assertEquals(['comment' => 'y'], $record->context);
        $this->assertEquals($post->getKey(), $record->transitionable->getKey());
    }

    public function testChargerDispatchesWithInjectedDispatcher()
    {
        $this->fakeAuth();

        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        $dispatcher = new EventDispatcher(Container::getInstance());
        $dispatcher->listen(TransitionCharged::class, [TransitionListener::class, 'handleCharged']);

        $transition = $post->state()->transitionTo(Enum::chargeable);
        $charger = $transition->charger($post->state())->dispatchWith($dispatcher);

        $charger->charge($transition, ['comment' => 'one']);

        $this->assertCount(1, $post->transitions()->get());
        $this->assertEquals('new', $post->transitions()->first()->source);
        $this->assertEquals(['comment' => 'one'], $post->transitions()->first()->context);
    }

    protected function fakeAuth(): void
    {
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new class()
        {
            public function guard($name = null)
            {
                return $this;
            }

            public function user()
            {
                return null;
            }
        });
    }

    public function testHandlingChargedWritesHistoryRecord()
    {
        $this->fakeAuth();

        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        $transition = $post->state()->transitionTo(Enum::chargeable);
        $context = new Context($transition, ['comment' => 'one']);

        (new TransitionListener())->handleCharged(new TransitionCharged($post->state(), $context));

        $record = $post->transitions()->first();

        $this->assertNotNull($record);
        $this->assertEquals('new', $record->source);
        $this->assertEquals(Enum::chargeable->value, $record->target);
        $this->assertEquals(['comment' => 'one'], $record->context);
        $this->assertEquals($post->getKey(), $record->transitionable->getKey());
    }

    public function testChargingLeavesHistoryFootprints()
    {
        $this->fakeAuth();

        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        // Route TransitionCharged to the listener, like the service provider does.
        $dispatcher = new EventDispatcher(Container::getInstance());
        $dispatcher->listen(TransitionCharged::class, [TransitionListener::class, 'handleCharged']);
        Container::getInstance()->instance(Dispatcher::class, $dispatcher);

        try {
            $state = $post->state();

            // Increments that do not complete the transition are logged.
            $state->transit(Enum::chargeable, ['comment' => 'one']);
            $state->transit(Enum::chargeable, ['comment' => 'two']);

            $this->assertTrue($state->is(Enum::new));

            $charges = $post->transitions()->get();

            $this->assertCount(2, $charges);
            foreach ($charges as $charge) {
                $this->assertEquals('new', $charge->source);
                $this->assertEquals(Enum::chargeable->value, $charge->target);
            }
            $this->assertEquals(['comment' => 'one'], $charges[0]->context);
            $this->assertEquals(['comment' => 'two'], $charges[1]->context);

            // The increment that completes the transition is covered by
            // the transition itself, so no further charge row is created.
            $state->transit(Enum::chargeable, ['comment' => 'three']);

            $this->assertTrue($state->is(Enum::chargeable));
            $this->assertCount(2, $post->transitions()->get());
        } finally {
            Container::getInstance()->forgetInstance(Dispatcher::class);
        }
    }

    public function testListenerFiltersRawFilesOutOfHistoryContext()
    {
        $this->fakeAuth();

        $file = UploadedFile::fake()->create('x.txt', 0);

        $state = State::make(Enum::new)->storing(function (Model $model, Context $context, TransitionHistory $log) {
            // Returned context still holds the raw file
            return $context->data()->all();
        });

        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        $context = new Context($state, ['comment' => 'y', 'file' => $file]);

        (new TransitionListener())->handleInitialization(new ModelInitialized($post->state(), $context));

        $record = $post->transitions()->first();

        $this->assertNotNull($record);
        // Only comment survives the filter
        $this->assertEquals(['comment' => 'y'], $record->context);
    }

    public function testListenerChainsStoringCallbacksThroughTransition()
    {
        $this->fakeAuth();

        $order = [];
        $record = null;

        $post = new Article();
        $post->setRawAttributes(['state' => Enum::new], true);

        $transition = $post->state()->transitionTo(Enum::review);
        $transition->storing(function (Model $model, Context $context, TransitionHistory $log) use (&$order, &$record) {
            $order[] = 'transition';
            $record = $log;

            return array_merge($context->data()->all(), ['transition' => 'processed']);
        });
        $transition->target()->storing(function (Model $model, Context $context, TransitionHistory $log) use (&$order, &$transition) {
            $order[] = 'target';
            // Target callback receives the transition as the contextual
            $this->assertSame($transition, $context->transition());

            return array_merge($context->data()->all(), ['target' => 'processed']);
        });

        $context = new Context($transition, ['comment' => 'y']);

        (new TransitionListener())->handleTransition(new ModelTransited($post->state(), $context));

        $this->assertEquals(['transition', 'target'], $order);
        $this->assertNotNull($record->getKey());
        $this->assertEquals('new', $record->source);
        $this->assertEquals('review', $record->target);
        // Target callback receives the transition's result
        $this->assertEquals(['comment' => 'y', 'transition' => 'processed', 'target' => 'processed'], $record->context);
    }

    public function testEngineSerializationRoundTrip()
    {
        $article = new Article();
        $article->state = Enum::review;
        $article->save();

        $engine = new StateMachine(new ArticleWorkflow(), $article, 'state');

        $restored = unserialize(serialize($engine));

        $this->assertInstanceOf(StateMachine::class, $restored);
        $this->assertEquals('state', $restored->attribute);
        $this->assertInstanceOf(ArticleWorkflow::class, $restored->blueprint);
        $this->assertNotSame($article, $restored->model);
        $this->assertEquals($article->getKey(), $restored->model->getKey());
        $this->assertTrue($restored->is(Enum::review));
    }
}