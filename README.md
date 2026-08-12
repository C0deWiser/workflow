# Workflow

* [Setup](#setup)
* [Consistency](#consistency)
* [Authorization](#authorization)
* [Chargeable Transitions](#chargeable-transitions)
* [Business Logic](#business-logic)
    * [Forbidden Transitions](#forbidden-transitions)
    * [Conditional Transitions](#conditional-transitions)
    * [User Provided Data](#additional-context)
* [JSON](#json-serialization)
* [Events](#events)
    * [EventListener](#eventlistener)
    * [Callback](#transition-callback)
* [Log Transitions](#transition-history) 

Package provides workflow functionality to Eloquent Models.

Workflow is a sequence of states, document evolve through.
Transitions between states inflicts the evolution road.

## Setup

First, describe the workflow blueprint with available states and transitions.
You MUST use enum values.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{
    public function states(): array
    {
        return Enum::cases();
    }
    
    public function transitions(): array
    {
        return [
            [Enum::new, Enum::review],
            [Enum::review, Enum::published],
            [Enum::review, Enum::correction],
            [Enum::correction, Enum::review]
        ];
    }
    
    public function authorization() : null|string|callable
    {
        return null; 
    }
}
```

Next, implement `Workflow` contract to a model. The model may have few 
workflows at the same time, so the method should return an array with 
attributes and associated blueprints.

```php
use \Codewiser\Workflow\Contracts\Workflow;
use \Codewiser\Workflow\StateMachine;
use \Codewiser\Workflow\WorkflowObserver;
use \Codewiser\Workflow\Example\ArticleWorkflow;
use \Codewiser\Workflow\Example\Enum;
use \Illuminate\Database\Eloquent\Attributes\ObservedBy;
use \Illuminate\Database\Eloquent\Model;

/**
 * @property Enum $state Current workflow state.
 */
 #[ObservedBy(WorkflowObserver::class)]
class Article extends Model implements Workflow
{   
    protected function casts(): array
    {
        return [
            'state' => Enum::class
        ];   
    }
    
    public function blueprints(): array
    {
        return [
            'state' => ArticleWorkflow::class
        ];   
    }
    
    public function state(): StateMachine
    {
        return new StateMachine(
            blueprint: new ArticleWorkflow(), 
            model: $this,
            attribute: 'state'
        );
    }
}
```

> We recommend to make a same-named method that will return 
> `StateMachine` object associated to the attribute. This method 
> provides fast access to state-machine functionality. It looks quite 
> Laravelish: property is for reading, method is for writing.

Do not forget to observe model with `WorkflowObserver`.

## Consistency

`WorkflowObserver` observes Model and keeps state machine consistency healthy.

```php
use \Codewiser\Workflow\Example\Article;
use \Codewiser\Workflow\Example\Enum;

// creating: will set proper initial state
$article = new Article();
$article->save();
assert($article->state === Enum::new);

// updating: will examine state machine consistency
$article->state = Enum::review;
$article->save();
// No exceptions thrown as such transition exists
assert($article->state === Enum::review);
```

## State and Transition objects

In an example above we describe blueprint with enum values, but actually they 
will be transformed to the objects. Those objects bring some additional 
functionality to the states and transitions, such as caption translations, 
transit authorization, routing rules, pre- and post-transition callbacks etc...

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\State;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{
    public function states(): array
    {
        return [
            State::make(Enum::new)->as(__('New')),
            State::make(Enum::review)->as(__('Review')),
            State::make(Enum::published)->as(__('Published')),
            State::make(Enum::correction)->as(__('Correction')),
        ];
    }
    
    public function transitions(): array
    {
        return [
            Transition::make(Enum::new, Enum::review)
                ->as(__('Send to review')),
            Transition::make(Enum::review, Enum::published)
                ->as(__('Publish')),
            Transition::make(Enum::review, Enum::correction)
                ->as(__('Correction required')),
            Transition::make(Enum::correction, Enum::review)
                ->as(fn(Article $article) => __('Review :name', [
                    'name' => $article->name
                ])),
        ];
    }
}
```

> If a Transition has no caption, it will use the caption of its target State.

## Authorization

As model's actions are not allowed to any user, as changing state is not 
allowed to any user. You may authorize transition in a conventional way.

When describing the workflow blueprint, we should implement `authorization` 
method. This method is used to authorize running transitions.

When method returns a `string`, this string will be applied to the policy 
method.

```php
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{   
    public function authorization() : null|string|callable
    {
        return 'transit'; 
    }
}
```

You may return a `callable` with custom authorization.

```php
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{   
    public function authorization() : null|string|callable
    {
        return function(Article $article, Transition $transition) {
            Gate::authorize('transit', [$article, $transition]);
        }; 
    }
}
```

### Authorizing Transition

When accepting user request, do not forget to authorize workflow state changing.

```php
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Transition;
use Illuminate\Http\Request;

public function update(Request $request, Article $article)
{
    Gate::authorize('update', $article);
    
    if ($state = $request->enum('state', Enum::class)) {
    
        // You may use standard policy call
        Gate::authorize('transit', [
            $article, 
            $article->state()->transitionTo($state)
        ]);
        
        // Or use helper
        $article->state()->authorize($state);
    }
    
    $article->fill($request->validated());
    
    $article->save();
}
```

### Authorized Transitions

To get only transitions that are authorized to the current user, use 
`authorized` filter of `TransitionCollection`.

Filter `authorized` may return `bool`, `Response` or throw an 
`AuthorizationException`.

```php
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Transition;

$article = new Article();

$transitions = $article->state()
    // Get available transitions
    ->transitions()
    // Filter only authorized transitions 
    ->authorized();
```

## Chargeable Transitions

Chargeable transition will fire only then accumulates some charge.
For example, we may want to publish an article only then at least three 
editors has accepted it.

```php
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Charge;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Transition;

Transition::make(Enum::review, Enum::publish)
    ->chargeable(Charge::make(
        progress: function(Article $article) {
            // Return float (0÷1) with charge progress.
            return $article->votes->count() / 3;
        },
        callback: function(Article $article, Context $context) {
            // Store transition charge increment.
            $article->votes->add($context->actor());
        })
        
        // Optional callback
        ->allow(function (Article $article, Context $context) {
            // Prevent charging twice!
            return $article->votes->doesntContain($context->actor());
        })
    );
```

## Business Logic

### Forbidden transitions

In some cases workflow routes may divide into branches. Way to go forced by
business logic, not user. User even shouldn't know about other ways.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\Exceptions\TransitionFatalException;

Transition::make(Enum::new, Enum::to_local_manager)
    ->when(fn(Order $model) => $model->amount <= 1000000); 

Transition::make(Enum::new, Enum::to_region_manager)
    ->unless(fn(Order $model) => $model->amount <= 1000000); 
```

User will see only one possible transition depending on order amount value.

> Transition becomes forbidden if its target State is forbidden too.

### Conditional transitions

Transition may have some conditions to run.
If model fits this conditions then the transition is possible.

If transition doesn't meet the condition, the callback should return 
human-readable description of a problem.

Here is an example of problems user may resolve.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\Exceptions\TransitionRecoverableException;

Transition::make(Enum::new, Enum::review)
    ->condition(function(Article $model) {
        if (strlen($model->body) < 1000) {
            return 'Your article should contain at least 1000 symbols. Then you may send it to review.'
        }
    })
    ->condition(function(Article $model) {
        if ($model->images->count() == 0) {
            return 'Your article should contain at least 1 image. Then you may send it to review.';
        }
    });
```

User will see the problematic transitions in a list of available transitions.
User follows instructions to resolve the issue and then may try to perform 
the transition again.

> Transition inherits conditions from its target State.

### Additional Context

Sometimes application requires an additional context to perform a transition.
For example, it may be a reason the article was rejected by the reviewer.

First, declare validation rules in transition or state definition:

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;

Transition::make(Enum::review, Enum::reject)
    ->withContext([
        'reason' => 'required|string|min:100'
    ]);
```

> Transition context rules includes the context rules of target State.

Next, set the context in the controller.

When creating a model:

```php
use Codewiser\Workflow\Example\Article;
use Illuminate\Http\Request;

public function store(Request $request)
{
    Gate::authorize('create', Article::class);
    
    $article = Article::query()->make(
        $request->all()
    );
    
    $article->state()
        // Init workflow, passing additional context
        ->init($request->all())
        // Now save model
        ->save();
}
```

When transiting model:

```php
use \Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Example\Article;
use Illuminate\Http\Request;

public function update(Request $request, Article $article)
{
    Gate::authorize('update', $article);
    
    if ($state = $request->enum('state', Enum::class)) {
        $article->state()
            // Authorize transition
            ->authorize($state)
            // Transit to the new state, passing additional context
            ->transit($state, $request->all())
            // Now save model
            ->save();        
    }
}
```

The context will be validated while saving, and you may catch a 
`ValidationException`.

After all you may handle this context in [events](#events).

## Additional Attributes

Sometimes we need to add some additional attributes to the workflow states 
and transitions. For example, we may group states by levels and use this 
information to color states and transitions in user interface.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\State;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{
    protected function transitions(): array
    {
        return [
            Transition::make(Enum::new, Enum::review)         
                ->attribute('level', 'warning'),
            Transition::make(Enum::review, Enum::published)   
                ->attribute('level', fn(Article $article) => 'success'),
            Transition::make(Enum::review, Enum::correction)  
                ->attributes([
                    'level' => 'danger'
                ]),
            Transition::make(Enum::correction, Enum::review)  
                ->attributes(fn(Article $article) => [
                    'level' => 'warning'
                ])
        ];
    }
}
```

> Transition will inherit attributes from its target State.

## JSON Serialization

For user to interact with model's workflow we should pass the data to a 
frontend of the application:

```php
use Illuminate\Http\Request;

public function state(\Codewiser\Workflow\Example\Article $article)
{    
    return $article->state()->toArray();
}
```

The payload will be like that:

```json
{
  "value": "review",
  "name": "Review",
  "transitions": [
    {
      "source": "review",
      "target": "publish",
      "name": "Publish",
      "issues": [
        "Publisher should provide a foreword."
      ],
      "level": "success"
    },
    {
      "source": "review",
      "target": "correction",
      "name": "Send to Correction",
      "rules": {
        "reason": ["required", "string", "min:100"]
      },
      "level": "danger"
    }
  ]
}
```

## Events

### State callback

You may define state callback(s), that will be called then state is reached.

Callback is a `callable` with `Model` and optional `Transition` arguments.

```php
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\State;
use Codewiser\Workflow\Transition;

State::make(Enum::correcting)
    ->withContext(['reason' => 'required|string|min:100'])
    ->saving(function (Article $article, Context $context) {
        $article->last_problem = $context->data()->get('reason');
    })
    ->saved(function(Article $article, Context $context) {
        $article->author->notify(
            new ArticleHasProblemNotification(
                $article, $context->data()->get('reason')
            )
        );
    }); 
```

### Transition Callback

You may define transition callback(s), that will be called after transition were successfully performed.

It is absolutely the same as State Callback.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Context;
use \Codewiser\Workflow\Transition;

Transition::make(Enum::review, Enum::correcting)
    ->withContext(['reason' => 'required|string|min:100'])
    ->saving(function (Article $article, Context $context) {
        $article->last_problem = $context->data()->get('reason');
    })
    ->saved(function(Article $article, Context $context) {
        $article->author->notify(
            new ArticleHasProblemNotification(
                $article, $context->data()->get('reason')
            )
        );
    }); 
```

You may define few callbacks to a single transition.

> State machine will invoke both sets of callbacks: from Transition and from 
> its target State.

### EventListener

Transition generates `ModelInitialized` and `ModelTransited` events.
You may define listener to handle it.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Events\ModelTransited;

class ModelTransitedListener
{
    public function handle(ModelTransited $event)
    {
        if ($event->model instanceof Article) {
            $article = $event->model;

            if ($event->context->target()->is(Enum::correction)) {
                // Article was send to correction, the reason described in context
                $article->author->notify(
                    new ArticleHasProblemNotification(
                        $article, $event->context->data()->get('reason')
                    )
                );
            }
        }
    }
}
```

## Transition History

The Package may log transitions to a database table. 

Register `\Codewiser\Workflow\WorkflowServiceProvider`.

Publish and run migrations:

    php artisan vendor:publish --tag=workflow-migrations
    php artisan migrate

It's done.

To get historical records, add `\Codewiser\Workflow\Traits\HasTransitionHistory` 
to a `Model` with workflow. It brings `transitions` relation.

Historical records presented by `\Codewiser\Workflow\Models\TransitionHistory` 
model, that holds information about transition performer, source and target 
states and a context, if it were provided.

Sometimes you may need to eager load the latest transition:

```php
Article::query()->withLatestTransition();
```

Or:

```php
$article->loadLatestTransition();
```

You may add a constraining:

```php
Article::query()->withLatestTransition(
    performer:      fn(MorphTo $builder) => $builder->withTrashed(),
    transitionable: fn(MorphTo $builder) => $builder->withTrashed()
);
```
