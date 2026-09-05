# Workflow

* [Setup](#setup)
* [Consistency](#consistency)
* [Authorization](#authorization)
* [Business Logic](#business-logic)
    * [Forbidden Transitions](#forbidden-transitions)
    * [Conditional Transitions](#conditional-transitions)
    * [User Provided Data](#additional-context)
    * [File Uploads](#file-uploads)
* [JSON](#json-serialization)
* [Events](#events)
    * [Callbacks](#callbacks)
    * [EventListener](#eventlistener)
* [Chargeable Transitions](#chargeable-transitions)
* [Log Transitions](#transition-history) 

Package provides workflow functionality to Eloquent Models.

Workflow is a sequence of states, a document evolves through.
Transitions between states inflict the evolution road.

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
}
```

Use `HasWorkflow` trait and make a method(s) that will return
`StateMachine` object associated with an attribute. There may be a few 
workflows at the same time. Each method MUST be marked with `Workflow` 
attribute.

Do not forget to observe model with `WorkflowObserver`.

```php
use Codewiser\Workflow\Attributes\Workflow;
use Codewiser\Workflow\Traits\HasWorkflow;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\WorkflowObserver;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

/**
 * @property Enum $state Current workflow state.
 */
#[ObservedBy(WorkflowObserver::class)]
class Article extends Model
{   
    use HasWorkflow;
    
    protected function casts(): array
    {
        return [
            'state' => Enum::class
        ];   
    }
    
    /**
     * @return StateMachine<self, Enum>
     */
    #[Workflow]
    public function state(): StateMachine
    {
        return $this->workflow(ArticleWorkflow::class, 'state');
    }
}
```

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

$article->state = Enum::new;
$article->save();
// throws TransitionException as such transition doesn't exist
```

## State and Transition objects

In the example above we describe blueprint with enum values, but actually they 
will be transformed to the special objects. Those objects bring some additional 
functionality to the states and transitions, such as human-readable captions, 
routing rules, pre- and post-transition callbacks etc...

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
            Transition::make(Enum::new, Enum::review),
            Transition::make(Enum::review, Enum::published),
            Transition::make(Enum::review, Enum::correction)
                // Set caption as a string
                ->as(__('Need correction')),
            Transition::make(Enum::correction, Enum::review)
                // Set caption with callable
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
allowed to any user.

When describing the workflow blueprint, you may implement `authorization` 
method. This method is used to authorize running transitions by default.

> If default authorization returns `null` — all transitions allowed to any user.

You may override `authorization` for every `Transition`.

Authorization callback may return `bool`, `Response` or throw an 
`AuthorizationException`.

```php
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{   
    public function authorization() : ?callable
    {
        // Default authorization for all transitions
        
        return fn(Article $article, Context $context) 
            => Gate::authorize('transit', [$article, $context->transition()]); 
    }
    
    public function transitions(): array
    {
        // Authorization for a single transition
    
        return [
            Transition::make(Enum::new, Enum::review)
                ->authorizedBy(fn(Article $article, Context $context) 
                    => Gate::authorize('transit', [$article, $context->transition()])
                );
        ];
    }
}
```

### Authorizing Transition

When accepting user request, do not forget to authorize workflow state changing.

```php
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Illuminate\Http\Request;

public function update(Request $request, Article $article)
{
    // Authorize update
    Gate::authorize('update', $article);

    if ($state = $request->enum('state', Enum::class)) {
        // Authorize transition to a new state
        $article->state()->authorize($state);

        // Apply the state change; save() will verify the transition
        $article->state = $state;
    }

    $article->fill($request->validated());

    $article->save();
}
```

### Authorized Transitions

To get only transitions that are authorized to the current user, use 
`authorized` filter of `TransitionCollection`. Then pass the list of allowed 
transitions to the front-end.

```php
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Transition;
use Illuminate\Http\Resources\Json\JsonResource;

public function show(Article $article)
{
    Gate::authorize('view', $article);

    return JsonResource::make($article)
        ->additional([
            'transitions' => $article->state()
                // Get available transitions
                ->transitions()
                // Filter only authorized transitions 
                ->authorized();
        ])
}
```

## Business Logic

### Forbidden transitions

In some cases workflow routes may divide into branches. Way to go is forced by
business logic, not by user. User even shouldn't know about other ways.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;

Transition::make(Enum::new, Enum::to_local_manager)
    ->when(fn(Order $model) => $model->amount <= 1000000); 

Transition::make(Enum::new, Enum::to_region_manager)
    ->unless(fn(Order $model) => $model->amount <= 1000000); 
```

User will see only one possible transition depending on order amount value.

> Transition becomes forbidden if its target State is forbidden too.

### Conditional transitions

Transition may have some conditions to run.
If the model fits these conditions then the transition is possible.

If transition doesn't meet the condition, the callback should return 
human-readable description of a problem.

Here is an example of problems user may resolve.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Transition;

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

User will see problematic transitions in a list of available transitions.
User follows instructions to resolve an issue and then may try to run 
a transition again.

> Transition inherits conditions from its target State.

### Additional Context

Sometimes a transition requires an additional context to run.
For example, it may be a reason why the article was rejected by the reviewer.

First, declare validation rules in the transition or state definition. You may 
declare just validation rules as an array, or use `Validation` object.

```php
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\Validation;

Transition::make(Enum::review, Enum::reject)
    ->context([
        'reason' => 'required|string|min:100'
    ]);
    
Transition::make(Enum::review, Enum::reject)
    ->context(Validation::rules([
            'reason' => 'required|string|min:100'
        ])->messages([
            'reason.required' => 'Describe why you rejecting the article.'
        ])
    );
```

> Transition context rules include the context rules of its target State.

Next, handle the context in the controller.

When creating a model:

```php
use Codewiser\Workflow\Example\Article;
use Illuminate\Http\Request;

public function store(Request $request)
{
    Gate::authorize('create', Article::class);
    
    $article = new Article();
    $article->fill($request->all());
    
    $article->state()
        // Init workflow with additional context
        ->init($request->all());
        
    // Now save model
    $article->save();
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

After that you may handle validated user data in [events](#events).

### File Uploads

Context may contain uploaded files, e.g. an article cover image or a proof
document for a claim.

First, declare the file key in the state or transition validation rules.
A key without a declared rule will not pass validation, so it never reaches
your callbacks.

```php
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Transition;

Transition::make(Enum::new, Enum::review)
    ->context([
        'cover' => 'file|image|max:2048'
    ]);
```

Pass the request with uploaded files to the state machine as usual:

```php
public function store(Request $request)
{
    $article = new Article();
    $article->fill($request->validated());

    $article->state()->init($request->all());

    $article->save();
}
```

> **Warning!** Uploaded files are NOT persisted themselves. The raw
> `UploadedFile` (and any other object) is filtered out of the context before
> it is written to the [transition history](#transition-history). You MUST
> store the file and replace it with the resulting handler (e.g. a path).

The best place to do it is a `storing` callback. It runs right before the
context is persisted into the transition history and may mutate the context:

```php
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Transition;

Transition::make(Enum::new, Enum::review)
    ->context(['cover' => 'file|image|max:2048'])
    // May be defined on a Transition, as on a State
    ->storing(function (Article $article, Context $context) {
        // Move the file to permanent storage
        $path = $context->data()->get('cover')->store('covers', 'public');

        // Replace the raw file with the stored handler
        $context->data()->set('cover', $path);
    });
```

> A `storing` callback does not affect the model or the `saving`/`saved`
> callbacks — it only tailors the context written to
> [transition history](#transition-history). If you need the path earlier
> (e.g. to attach it to the model), handle the file in a
> [`saving`](#callbacks) callback instead and put the path into the context
> the same way.

## Additional Attributes

Sometimes we need to add some additional attributes (not only caption) to the 
workflow states and transitions. For example, we may group states by levels 
and use this information to color states and transitions in user interface.

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
                // Set single attribute as a string         
                ->attribute('level', 'warning'),
            Transition::make(Enum::review, Enum::published)
                // Set single attribute with callable   
                ->attribute('level', fn(Article $article) => 'success'),
            Transition::make(Enum::review, Enum::correction)
                // Set multiple attributes with array  
                ->attributes([
                    'level' => 'danger'
                ]),
            Transition::make(Enum::correction, Enum::review)
                // Set multiple attributes with callable  
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
front-end of the application. `StateMachine` object is `Arrayable`, so 
passing it is enough.

```php
use Codewiser\Workflow\Example\Article;
use Illuminate\Http\Resources\Json\JsonResource;

public function view(Article $article)
{    
    return JsonResource::make($article)
        ->additional([
            'state' => $article->state()
        ]);
}
```

The payload of `StateMachine` object will be like that. We hope this is 
enough to build a user interface.

```json
{
  "value": "review",
  "name": "Review",
  "transitions": [
    {
      "source": "review",
      "target": "published",
      "name": "Publish",
      "issues": [
        "Publisher should provide a foreword."
      ],
      "level": "success"
    },
    {
      "source": "review",
      "target": "correction",
      "name": "Need correction",
      "context": {
          "rules": {
            "reason": ["required", "string", "min:100"]
          },
          "messages": {
            "reason.required": "Describe why you rejecting the article."
          }
      },
      "level": "danger"
    }
  ]
}
```

## Events

### Callbacks

You may define state callback(s), that will be called when the state is reached. 
A callback is a `callable` with `Model` and optional `Context` arguments.
A callback may be defined on a `Transition` as well as on a `State`.

There are two types of callbacks: `saving` and `saved`. It is absolutely the 
same as well-known Eloquent events.

```php
use \Codewiser\Workflow\Example\Enum;
use \Codewiser\Workflow\Context;
use \Codewiser\Workflow\Transition;

Transition::make(Enum::review, Enum::correcting)
    ->context(['reason' => 'required|string|min:100'])
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

You may define a few callbacks to a single transition.

> State machine will invoke both sets of callbacks: from Transition and from 
> its target State.

### EventListener

Transition generates `ModelInitialized` and `ModelTransited` events.
You may define listeners to handle them.

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
                // Article was sent to correction, the reason described in context
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

## Chargeable Transitions

A chargeable transition fires only when some charge accumulates.
For example, we may want to publish an article only after at least three
editors have accepted it.

```php
use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\Charger;
use Codewiser\Workflow\Context;
use Codewiser\Workflow\Transition;

Transition::make(Enum::review, Enum::publish)
    ->context(['comment' => 'required'])
    ->chargeable(Charger::make(
        progress: function(Article $article) {
            // Return float (0÷1) with charge progress.
            return $article->votes->count() / 3;
        },
        callback: function(Article $article, Context $context) {
            // Store transition charge increment.
            $article->votes->add(auth()->user());
        })
        
        // Optional callbacks
        
        ->allow(function (Article $article, Context $context) {
            // Prevent charging twice!
            return $article->votes->doesntContain(auth()->user());
        })
        ->withHistory(function (Article $article, Context $context) {
            // Provide votes history to a front-end
            return $article->votes->toArray();
        })
    );
```

## Transition History

The package may log transitions to a database table. 

Register `\Codewiser\Workflow\WorkflowServiceProvider`.

Publish and run migrations:

    php artisan vendor:publish --tag=workflow-migrations
    php artisan migrate

It's done.

To get historical records, add `\Codewiser\Workflow\Traits\HasTransitionHistory` 
to a `Model` with workflow. It brings `transitions` relation.

Historical records are presented by `\Codewiser\Workflow\Models\TransitionHistory` 
model, that holds information about the transition performer, source and target 
states, and the context, if it was provided.

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
