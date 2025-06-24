# Workflow

* [Setup](#setup)
* [Consistency](#consistency)
* [Authorization](#authorization)
* [Chargeable Transitions](#chargeable-transitions)
* [Business Logic](#business-logic)
    * [Disabling Transitions](#disabling-transitions)
    * [Removing Transitions](#removing-transitions)
    * [User Provided Data](#additional-context)
* [Translations](#translations)
* [JSON](#json-serialization)
* [Events](#events)
    * [EventListener](#eventlistener)
    * [Callback](#transition-callback)
* [Log Transitions](#transition-history) 

Package provides workflow functionality to Eloquent Models.

Workflow is a sequence of states, a document evolved through.
Transitions between states inflicts the evolution road.

## Setup

First, describe the workflow blueprint with available states and transitions:

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
}
```

Next, include trait and create method to bind a blueprint to model's attribute.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Example\ArticleWorkflow;
use \Codewiser\Workflow\StateMachineEngine;
use \Codewiser\Workflow\Traits\HasWorkflow;
use \Illuminate\Database\Eloquent\Model;

/**
 * @property Enum $state
 */
class Article extends Model
{
    use HasWorkflow;
    
    protected function casts(): array
    {
        return [
            'state' => Enum::class
        ]
    }
    
    public function state(): StateMachineEngine
    {
        return $this->workflow(ArticleWorkflow::class, 'state');
    }
}
```

That's it.

## Consistency

Workflow observes Model and keeps state machine consistency healthy.

```php
use \Codewiser\Workflow\Example\Enum;

// creating: will set proper initial state
$article = new Article();
$article->save();
assert($article->state == Enum::new);

// updating: will examine state machine consistency
$article->state = Enum::review;
$article->save();
// No exceptions thrown as transition from `new` to `review` exists
assert($article->state == Enum::review);
```

## State and Transition objects

In an example above, we describe blueprint with enum values, but actually they 
will be transformed to the objects. Those objects bring some additional 
functionality to the states and transitions, such as caption translations, 
transit authorization, routing rules, pre- and post-transition callbacks, etc...

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
            State::make(Enum::new),
            State::make(Enum::review),
            State::make(Enum::published),
            State::make(Enum::correction),
        ];
    }
    
    public function transitions(): array
    {
        return [
            Transition::make(Enum::new, Enum::review),
            Transition::make(Enum::review, Enum::published),
            Transition::make(Enum::review, Enum::correction),
            Transition::make(Enum::correction, Enum::review),
        ];
    }
}
```

## Authorization

As model's actions are not allowed to any user, as changing state is not 
allowed to any user. You may define transition authorization rules either 
using `Policy` or using `callable`.

### Using Policy

Provide ability name. The package will examine given ability against 
an associated model.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;

Transition::make(Enum::new, Enum::review)->authorizedBy('transit');

class ArticlePolicy
{
    public function transit(User $user, Article $article, Transition $transition)
    {
        //
    }
}
```

### Using Closure

Authorization Closure should return `true` or `false`. 

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;
use \Illuminate\Support\Facades\Gate;

Transition::make(Enum::new, Enum::review)
    ->authorizedBy(function(Article $article, Transition $transition): bool {
        return Gate::allows('transit', [$article, $transition]);
    });
```

### Authorized Transitions

To get only transitions, authorized to the current user, use `authorized` 
method of `TransitionCollection`:

```php
$article = new Article();

$transitions = $article->state()
    // Get transitions from a model's current state.
    ->transitions()
    // Filter only authorized transitions. 
    ->onlyAuthorized();
```

### Authorizing Transition

When accepting a user request, remember to authorize workflow state changing.

```php
use \Codewiser\Workflow\Example\Enum;
use \Illuminate\Http\Request;

public function update(Request $request, Article $article)
{
    $this->authorize('update', $article);
    
    if ($state = $request->enum('state', Enum::class)) {
        // Check if user allowed to make this transition
        $article->state()->authorize($state);
    }
    
    $article->fill($request->validated());
    
    $article->save();
}
```

## Chargeable Transitions

Chargeable transition will fire only then accumulate some charge. For 
example, we may want to publish an article only then at least three editors 
will accept it.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Charge;
use \Codewiser\Workflow\Transition;

Transition::make(Enum::review, Enum::publish)
    ->chargeable(Charge::make(
        progress: function(Article $article) {
            return $article->accepts / 3;
        },
        callback: function (Article $article) {
            $article->accepts++;
            $article->save();
        }
    ));
```

`Charge` class has more options, that allows to provide vote statistics or 
prevent to vote twice. 

## Business Logic

### Disabling transitions

Transition may have some prerequisites to a model. If the model fits these 
conditions, then the transition is possible.

Prerequisite is a `callable` with `Model` argument. It may throw an exception.

To temporarily disable transition, prerequisite should throw a 
`TransitionRecoverableException`. Leave helping instructions in an exception 
message.

Here is an example of issues user may resolve.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\Exceptions\TransitionRecoverableException;

Transition::make(Enum::new, Enum::review)
    ->before(function(Article $model) {
        if (strlen($model->body) < 1000) {
            throw new TransitionRecoverableException(
                'Your article should contain at least 1000 symbols. Then you may send it to review.'
            );
        }
    })
    ->before(function(Article $model) {
        if ($model->images->count() == 0) {
            throw new TransitionRecoverableException(
                'Your article should contain at least 1 image. Then you may send it to review.'
            );
        }
    });
```

Users will see the problematic transitions in a list of available transitions.
User follows instructions to resolve the issue and then may try to perform the transition again.

### Removing transitions

In some cases, workflow routes may be divided into branches. Way to go 
forced by business logic, not user. Users even shouldn't know about other ways.

To completely remove transition from a list, prerequisite should throw a 
`TransitionFatalException`.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\Exceptions\TransitionFatalException;

Transition::make(Enum::new, Enum::toLocalManager)
    ->before(function(Order $model) {
        if ($model->amount >= 1000000) {
            throw new TransitionFatalException("Order amount is too big for this transition.");
        }
    }); 

Transition::make(Enum::new, Enum::toRegionManager)
    ->before(function(Order $model) {
        if ($model->amount < 1000000) {
            throw new TransitionFatalException("Order amount is too small for this transition.");
        }
    }); 
```

User will see only one possible transition depending on order amount value.

### Additional Context

Sometimes application requires an additional context to perform a transition.
For example, it may be a reason the article was rejected by the reviewer.

First, declare validation rules in transition or state definition:

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;

Transition::make(Enum::review, Enum::reject)
    ->rules([
        'reason' => 'required|string|min:100'
    ]);
```

Next, set the context in the controller.

When creating a model:

```php
use Illuminate\Http\Request;

public function store(Request $request)
{
    $article = Article::query()->make($request->validated());
    
    $article->state()
        // Init workflow, passing additional context
        ->init($request->only('reason'))
        // Now save model
        ->save();
}
```

When transiting model:

```php
use \Codewiser\Workflow\Example\Enum;
use Illuminate\Http\Request;

public function update(Request $request, Article $article)
{
    if ($state = $request->enum('state', Enum::class)) {
        $article->state()
            // Authorize transition
            ->authorize($state)
            // Transit to the new state, passing additional context
            ->transit($state, $request->only('reason'))
            // Now save model
            ->save();        
    }
}
```

The context will be validated while saving, and you may catch a `ValidationException`.

After all, you may handle this context in [events](#events).

## Translations

You may define `State` and `Transition` objects with translatable caption. 
Or you may implement `\Codewiser\Workflow\Contracts\StateEnum` to `enum`.

`Transition` without caption will inherit caption from its target `State`.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\State;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{
    protected function states(): array
    {
        return [
            State::make(Enum::new)->as('Draft'),
            State::make(Enum::published)->as('Published')
        ];
    }
    protected function transitions(): array
    {
        return [
            Transition::make(Enum::new, Enum::published)->as('Publish')
        ];
    }
}
```

## Additional Attributes

Sometimes we need to add some additional attributes to the workflow states 
and transitions. For example, we may group states by levels and use this 
information to color states and transitions in the user interface. Also, you 
may implement `\Codewiser\Workflow\Contracts\StateEnum` to `enum`.

`Transition` inherits attributes from its target `State`.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\State;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{
    protected function states(): array
    {
        return [
            State::make(Enum::new),
            State::make(Enum::review)->set('level', 'warning'),
            State::make(Enum::published)->set('level', 'success'),
            State::make(Enum::correction)->set('level', 'danger')
        ];
    }
    protected function transitions(): array
    {
        return [
            Transition::make(Enum::new, Enum::review)->set('level', 'warning'),
            Transition::make(Enum::review, Enum::published)->set('level', 'success'),
            Transition::make(Enum::review, Enum::correction)->set('level', 'danger'),
            Transition::make(Enum::correction, Enum::review)->set('level', 'warning')
        ];
    }
}
```

## Json Serialization

For user to interact with model's workflow, we should pass the data to the 
frontend of the application:

```php
use Illuminate\Http\Request;

public function state(Article $article)
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

### State Callback

You may define state callback(s), that will be called then state is reached.

Callback is a `callable` with `Model` and `Context` arguments.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Context;
use \Codewiser\Workflow\State;
use \Codewiser\Workflow\Transition;

State::make(Enum::correcting)
    ->rules(['reason' => 'required|string|min:100'])
    ->after(function(Article $article, Context $context) {
        $article->author->notify(
            new ArticleHasProblemNotification(
                $article, $context->data()->get('reason')
            )
        );
    }); 
```

### Transition Callback

You may define transition callback(s), that will be called after transition 
was successfully performed.

It is absolutely the same as State Callback.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Context;
use \Codewiser\Workflow\Transition;

Transition::make(Enum::review, Enum::correcting)
    ->rules(['reason' => 'required|string|min:100'])
    ->after(function(Article $article, Context $context) {
        $article->author->notify(
            new ArticleHasProblemNotification(
                $article, $context->data()->get('reason')
            )
        );
    }); 
```

You may define a few callbacks to a single transition.

### EventListener

Transition generates `ModelTransited` event. You may define `EventListener` 
to listen to it.

```php
use \Codewiser\Workflow\Events\ModelTransited;

class ModelTransitedListener
{
    public function handle(ModelTransited $event)
    {
        if ($event->model instanceof Article) {
            $article = $event->model;

            if ($event->context->target()->is('correction')) {
                // The article was sent to correction, the reason described in context
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

Sometimes you may need to eagerly load the latest transition:

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

Or you may override a default constraining in a model:

```php
use Codewiser\Workflow\Traits\HasTransitionHistory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasTransitionHistory;
    
    protected function getLatestTransitionConstraining(
        ?\Closure $performer = null, 
        ?\Closure $transitionable = null
    ) : array
    {
        $performer = $performer ?? 
            fn(MorphTo $builder) => $builder->withTrashed();
            
        $transitionable = $transitionable ?? 
            fn(MorphTo $builder) => $builder->withTrashed();
        
        return [
            'latest_transition' => [
                'performer'      => $performer,
                'transitionable' => $transitionable
            ]
        ];
    }
}
```