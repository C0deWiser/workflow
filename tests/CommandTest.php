<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Example\Order;
use Codewiser\Workflow\Attributes\Workflow;
use Codewiser\Workflow\Console\ShowWorkflowCommand;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;
use Codewiser\Workflow\WorkflowServiceProvider;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends TestCase
{
    private $previousContainer;

    private ?Container $container = null;

    public static function setUpBeforeClass(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->bootEloquent();

        $schema = $capsule->getConnection()->getSchemaBuilder();

        $schema->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('state')->nullable();
            $table->text('votes')->nullable();
            $table->boolean('condition')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
        });

        $schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->string('notify')->nullable();
            $table->timestamps();
        });

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function setUp(): void
    {
        Relation::morphMap([
            'article' => Article::class,
            'order'   => Order::class,
            'user'    => User::class,
        ]);

        // Run the command inside its own container
        $this->previousContainer = Container::getInstance();

        Container::setInstance($this->container = new class() extends Container
        {
            /**
             * The command only asks the container for prompts configuration.
             */
            public function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    public function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        Relation::morphMap([], false);
    }

    public function testShowsStateAndTransitionsByMorphAlias()
    {
        $post = new Article();
        $post->state = Enum::new;
        $post->condition = true;
        $post->save();

        [$code, $display] = $this->artisan(['model' => 'article#'.$post->getKey()]);

        $this->assertSame(0, $code);

        $this->assertStringContainsString(
            'Model: '.Article::class.' #'.$post->getKey().' (as article)',
            $display
        );
        $this->assertStringContainsString('Workflow [state]', $display);
        $this->assertStringContainsString('State: new', $display);

        // Blocked by a condition, with the problem to resolve
        $this->assertMatchesRegularExpression(
            '/\|\s+review\s+\|\s+Bad condition\s+\|\s+blocked\s+\|\s+Incomplete\s+\|/',
            $display
        );

        // Forbidden by a dead end
        $this->assertMatchesRegularExpression(
            '/\|\s+published\s+\|\s+Forbidden transition\s+\|\s+forbidden\s+\|/',
            $display
        );

        // Unauthorized, but still listed to explain why it is not offered
        $this->assertMatchesRegularExpression(
            '/\|\s+prohibited\s+\|\s+prohibited\s+\|\s+unauthorized\s+\|/',
            $display
        );

        // Chargeable and ready to run, with its charging level
        $this->assertMatchesRegularExpression(
            '/\|\s+cumulative\s+\|\s+chargeable\s+\|\s+available\s+\|/',
            $display
        );
        $this->assertStringContainsString('0%', $display);
    }

    public function testShowsStateAndTransitionsByClassWithEmbeddedId()
    {
        $post = new Article();
        $post->state = Enum::review;
        $post->save();

        [$code, $display] = $this->artisan([
            'model' => Article::class.'#'.$post->getKey(),
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Model: '.Article::class.' #'.$post->getKey(), $display);
        $this->assertStringNotContainsString('(as ', $display);
        $this->assertStringContainsString('State: review', $display);

        // Ways out of the review state
        $this->assertMatchesRegularExpression('/\|\s+published\s+\|/', $display);
        $this->assertMatchesRegularExpression('/\|\s+correction\s+\|/', $display);
    }

    public function testShowsEveryWorkflowOfModel()
    {
        $order = new Order();
        $order->status = Enum::review;
        $order->save();

        [$code, $display] = $this->artisan(['model' => 'order#'.$order->getKey()]);

        $this->assertSame(0, $code);

        $this->assertStringContainsString('Workflow [status]', $display);
        $this->assertStringContainsString('State: review', $display);

        $this->assertStringContainsString('Workflow [notify]', $display);
        $this->assertStringContainsString('State is not initialized.', $display);
    }

    public function testAttrOptionShowsOnlyGivenWorkflow()
    {
        $order = new Order();
        $order->status = Enum::review;
        $order->save();

        [$code, $display] = $this->artisan([
            'model'   => 'order#'.$order->getKey(),
            '--attr'  => 'status',
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Workflow [status]', $display);
        $this->assertStringNotContainsString('Workflow [notify]', $display);
    }

    public function testAttrOptionRejectsUnknownWorkflow()
    {
        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        [$code, $display] = $this->artisan([
            'model'   => 'article#'.$post->getKey(),
            '--attr'  => 'missing',
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Model has no workflow [missing]. Available: state', $display);
    }

    public function testShowsDeadEndStateWithoutTransitions()
    {
        $post = new Article();
        $post->state = Enum::unreacheable;
        $post->save();

        [$code, $display] = $this->artisan(['model' => 'article#'.$post->getKey()]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('State: empty (unreacheable)', $display);
        $this->assertStringContainsString('No transitions from this state.', $display);
    }

    public function testShowsUninitializedState()
    {
        $post = new Article();
        $post->save();

        [$code, $display] = $this->artisan(['model' => 'article#'.$post->getKey()]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('State is not initialized.', $display);
    }

    public function testFailsWhenModelReferenceIsUnknown()
    {
        [$code, $display] = $this->artisan(['model' => 'widget#1']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Unable to resolve model [widget].', $display);
        $this->assertStringContainsString('Morph map: article, order, user', $display);
    }

    public function testFailsWhenReferenceIsNotAModel()
    {
        [$code, $display] = $this->artisan(['model' => 'stdClass#1']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('[stdClass] is not an Eloquent model.', $display);
    }

    public function testFailsWhenIdIsMissing()
    {
        [$code, $display] = $this->artisan(['model' => 'article']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Model id is missing. Try `workflow:show article#1`.', $display);
    }

    public function testFailsWhenModelIsNotFound()
    {
        [$code, $display] = $this->artisan(['model' => 'article#999']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Model [article#999] was not found.', $display);
    }

    public function testCommandIsRegisteredInConsole()
    {
        $previous = Container::getInstance();

        try {
            $app = new Application(dirname(__DIR__));
            $app->instance('env', 'testing');

            Facade::setFacadeApplication($app);

            $provider = new WorkflowServiceProvider($app);
            $provider->register();
            $provider->boot();

            $artisan = new ConsoleApplication($app, $app->make('events'), $app->version());

            $this->assertTrue($artisan->has('workflow:show'));

            $definition = $artisan->find('workflow:show')->getDefinition();

            $this->assertArrayHasKey('model', $definition->getArguments());
            $this->assertArrayHasKey('as', $definition->getOptions());
            $this->assertArrayHasKey('attr', $definition->getOptions());
        } finally {
            Container::setInstance($previous);
            Facade::setFacadeApplication(null);
            Facade::clearResolvedInstances();
        }
    }

    public function testInspectsAsAuthenticatable()
    {
        $user = new User();
        $user->save();

        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        $auth = new FakedAuth();
        $this->container->instance(Factory::class, $auth);

        [$code, $display] = $this->artisan([
            'model' => 'article#'.$post->getKey(),
            '--as'  => 'user#'.$user->getKey(),
        ]);

        $this->assertSame(0, $code);

        $this->assertStringContainsString(
            'As: '.User::class.' #'.$user->getKey().' (as user)',
            $display
        );

        // The workflow is inspected from the point of view of this user
        $this->assertSame($user->getKey(), $auth->user()?->getKey());
    }

    public function testAuthenticatedPointOfViewChangesAuthorization()
    {
        $owner = new User();
        $owner->save();

        $someone = new User();
        $someone->save();

        $post = new OwnedArticle();
        $post->state = Enum::new;
        $post->owner_id = $owner->getKey();
        $post->save();

        $auth = new FakedAuth();
        $this->container->instance(Factory::class, $auth);

        // Not authenticated: the transition is unauthorized
        [$code, $display] = $this->artisan(['model' => OwnedArticle::class.'#'.$post->getKey()]);

        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('/\|\s+review\s+\|\s+review\s+\|\s+unauthorized\s+\|/', $display);
        $this->assertNull($auth->user());

        // Authenticated as someone else: still unauthorized
        [$code, $display] = $this->artisan([
            'model' => OwnedArticle::class.'#'.$post->getKey(),
            '--as'  => 'user#'.$someone->getKey(),
        ]);

        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('/\|\s+review\s+\|\s+review\s+\|\s+unauthorized\s+\|/', $display);
        $this->assertSame($someone->getKey(), $auth->user()?->getKey());

        // Authenticated as the owner: available
        [$code, $display] = $this->artisan([
            'model' => OwnedArticle::class.'#'.$post->getKey(),
            '--as'  => 'user#'.$owner->getKey(),
        ]);

        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('/\|\s+review\s+\|\s+review\s+\|\s+available\s+\|/', $display);
        $this->assertSame($owner->getKey(), $auth->user()?->getKey());
        $this->assertStringContainsString('As: '.User::class.' #'.$owner->getKey().' (as user)', $display);
    }

    public function testFailsWhenAuthenticatableIdIsMissing()
    {
        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        $auth = new FakedAuth();
        $this->container->instance(Factory::class, $auth);

        [$code, $display] = $this->artisan([
            'model' => 'article#'.$post->getKey(),
            '--as'  => 'user',
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Authenticatable id is missing. Try `--as=user#1`.', $display);
    }

    public function testFailsWhenAuthIsNotAvailable()
    {
        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        // No auth factory is bound to the container
        [$code, $display] = $this->artisan([
            'model' => 'article#'.$post->getKey(),
            '--as'  => 'user#1',
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString(
            'Unable to authenticate: ['.Factory::class.'] is not bound in the container.',
            $display
        );
    }

    public function testFailsWhenAuthenticatableIsNotFound()
    {
        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        $auth = new FakedAuth();
        $this->container->instance(Factory::class, $auth);

        [$code, $display] = $this->artisan([
            'model' => 'article#'.$post->getKey(),
            '--as'  => 'user#999',
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Authenticatable [user#999] was not found.', $display);
    }

    public function testFailsWhenAuthenticatableIsNotAuthenticatable()
    {
        $post = new Article();
        $post->state = Enum::new;
        $post->save();

        $auth = new FakedAuth();
        $this->container->instance(Factory::class, $auth);

        [$code, $display] = $this->artisan([
            'model' => 'article#'.$post->getKey(),
            '--as'  => 'article#'.$post->getKey(),
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('['.Article::class.'] is not Authenticatable.', $display);
    }

    /**
     * Run the command, get the exit code and the output.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: string}
     */
    private function artisan(array $input): array
    {
        $command = new ShowWorkflowCommand();
        $command->setLaravel($this->container);

        $tester = new CommandTester($command);
        $code = $tester->execute($input);

        return [$code, $tester->getDisplay()];
    }
}

/**
 * A workflow, where only the owner may send an article to review.
 */
class OwnedArticleWorkflow extends WorkflowBlueprint
{
    public function states(): array
    {
        return [Enum::new, Enum::review];
    }

    public function transitions(): array
    {
        return [
            Transition::make(Enum::new, Enum::review)
                ->authorizedBy(fn(Article $article) => auth()->user()?->getKey() === (int) $article->getAttribute('owner_id')),
        ];
    }
}

/**
 * An article, whose workflow is guarded by the article owner.
 */
class OwnedArticle extends Article
{
    protected $table = 'articles';

    #[Workflow]
    public function state(): StateMachine
    {
        return $this->workflow(OwnedArticleWorkflow::class, 'state');
    }
}

/**
 * Minimal auth factory + guard, recording the authenticated user.
 */
class FakedAuth
{
    public ?Authenticatable $user = null;

    public function guard($name = null): static
    {
        return $this;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function id()
    {
        return $this->user?->getAuthIdentifier();
    }
}
