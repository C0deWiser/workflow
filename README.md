# Workflow

* [Setup](#setup)
* [Consistency](#consistency)
* [Authorization](#authorization)
* [Business Logic](#business-logic)
  * [Disabling Transitions](#disabling-transitions)
  * [Removing Transitions](#removing-transitions)
  * [User Provided Data](#additional-context)
* [Translations](#translations)
* [JSON](#json-serialization)
* [Events](#events)
  * [EventListener](#eventlistener)
  * [Observer](#observer)
  * [Callback](#transition-callback)

Package provides workflow functionality to Eloquent Models.

Workflow is a sequence of states, document evolve through. 
Transitions between states inflicts the evolution road.

## Setup

First, declare backed (`string` or `int`) `enum` for model states.

```php
enum State: string
{
    case new = 'new';
    case review = 'review';
    case published = 'published';
    case correction = 'correction';
}
```

Second, describe the workflow blueprint.

```php
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{
    protected function states(): array
    {
        return [
            State::new,
            State::review,
            State::published,
            State::correction
        ];
    }
    protected function transitions(): array
    {
        return [
            Transition::define(State::new, State::review),
            Transition::define(State::review, State::published),
            Transition::define(State::review, State::correction),
            Transition::define(State::correction, State::review)
        ];
    }
}
```

Second, apply workflow to the model.

```php
use \Codewiser\Workflow\Traits\HasWorkflow;

class Article extends Model
{
    use HasWorkflow;
    
    public $workflow = [
        'state' => ArticleWorkflow::class
    ];
}
```

Workflow keeps its state in model attribute you provide as a key of array.
You should migrate model schema to add this column.

You may apply few independent workflow blueprints to one model.

## Consistency

Workflow observes Model and keeps state machine consistency healthy.

```php
// creating: will set proper initial state
$article = new Article();
$article->save();

// updating: will examine state machine consistency
$article->state = State::review;
$article->save();
```

## Authorization

As model's actions are not allowed to any user, as changing state is not allowed to any user. You may define transition authorization rules either using `Policy` or using `callable`. 

### Using Policy

Add policy action:

```php
class ArticlePolicy 
{
    public function transitToReview(User $user, Article $article)
    {
        return $user->is($article->author);
    }
}
```

And provide action name to `Transition::authorizedBy()`:

```php
use \Codewiser\Workflow\Transition;

Transition::define(State::new, State::review)
    ->authorizedBy('transitToReview');
```

### Using Closure

```php
use \Codewiser\Workflow\Transition;
use \Illuminate\Support\Facades\Gate;

Transition::define(State::new, State::review)
    ->authorizedBy(function (Article $article) {
        return Gate::allows('transitToReview', $article);
    });
```

### Authorized Transitions

To get only transitions, authorized to the current user, use `authorized` method of `TransitionCollection`:

```php
$transitions = $article->workflow()
    // Get transitions from model's current state.
    ->routes()
    // Filter only authorized transitions. 
    ->authorized();
```

### Authorizing Transition

When accepting user request, do not forget to authorize workflow state changing.

```php
public function update(Request $request, Article $article)
{
    $this->authorize('update', $article);
    
    if ($state = $request->get('state')) {
        $article->workflow()->authorize(State::from($state));
        $article->state = $state;
    }
    
    $article->save();
}
```

## Business Logic

### Disabling transitions 

Transition may have some prerequisites to a model. If model fits this conditions then the transition can be performed.

Prerequisite is a `callable` with `Model` argument. It may throw an exception.

To disable transition, prerequisite should throw a `TransitionRecoverableException`. Leave helping instructions in exception message. 

Here is an example of issues user may resolve.

```php
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\Exceptions\TransitionRecoverableException;

Transition::define(State::new, State::review)
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

User will see the problematic transitions in a list of available transitions. 
User follows instructions to resolve the issue and then may to perform the transition.

### Removing transitions

In some cases workflow routes may divide into branches. Way to go forced by business logic, not user.
User even shouldn't know about other ways.

To completely remove transition, prerequisite should throw a `TransitionFatalException`.

```php
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\Exceptions\TransitionFatalException;

Transition::define(State::new, State::toLocalManager)
    ->before(function($model) {
        if ($model->orderAmount >= 1000000) {
            throw new TransitionFatalException();
        }
    }); 

Transition::define(State::new, State::toRegionManager)
    ->before(function($model) {
        if ($model->orderAmount < 1000000) {
            throw new TransitionFatalException();
        }
    }); 
```

User will see only one possible transition depending on order amount value.

### Additional Context

Sometimes application requires an additional context to perform a transition. For example, it may be a reason the article was rejected by the reviewer.

First, declare validation rules in transition definition:

```php
use \Codewiser\Workflow\Transition;

Transition::define(State::review, State::reject)
    ->rules([
        'reason' => 'required|string|min:100'
    ]);
```

Next, set the transition context in the controller:

```php
use Illuminate\Http\Request;

public function update(Request $request, Article $article)
{
    $this->authorize('update', $article);
    
    if ($state = $request->get('state')) {
        $article->workflow()->authorize(State::from($state));
        $article->workflow()->context($request->all());

        $article->state = $state;        
    }
    
    $article->save();
}
```

The context will be validated while saving, and you may catch a `ValidationException`.

After all you may handle this context in [events](#events).

## Translations

You may define `State` and `Transition` objects with translatable caption.

```php
enum State: string
{
    case new = 'new';
    case review = 'review';
    case published = 'published';
    case correction = 'correction';
    
    public function caption(): string
    {
        return __($this->name);
    }
}
```

```php
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{
    protected function transitions(): array
    {
        return [
            Transition::define(State::new, State::published)
                ->as(__('Publish'))
        ];
    }
}
```

## Additional Attributes

Sometimes we need to add some additional attributes to the workflow states. For example, we may group states by levels and use this information to color states and transitions in user interface.

```php
enum State: string
{
    case new = 'new';
    case review = 'review';
    case published = 'published';
    case correction = 'correction';
    
    public function attributes(): array
    {
        return match($this) {
            self::new        => [],
            self::review     => ['level' => 'warning'],
            self::published  => ['level' => 'success'],
            self::correction => ['level' => 'danger']
        };
    }
}
```

## Json Serialization

For user to interact with model's workflow we should pass the data to a frontend of application:

```php
use Illuminate\Http\Request;

public function show(Article $article)
{
    $this->authorize('view', $article);
    
    $data = $article->toArray();
    
    $data['state'] => [
        'current' => $article->workflow()->toArray(),
        'transitions' => $article->workflow()
            ->routes()
            ->authorized()
            ->toArray()
    ];
    
    return $data;
}
```

The payload will be like that:

```json
{
  "id": 1,
  "title": "Article title",
  "state": {
    "current": {
      "value": "review",
      "caption": "Review",
      "level": "warning"
    },
    "transitions": [
      {
        "source": "review",
        "target": "publish",
        "caption": "Publish",
        "problems": ["Publisher should provide a foreword."],
        "requires": []
      },
      {
        "source": "review",
        "target": "correction",
        "caption": "Send to Correction",
        "problems": [],
        "requires": ["reason"]
      }
    ]
  }
}
```

## Events

### EventListener

Transition generates `ModelTransited` event. You may define `EventListener` to listen to it.

```php
use \Codewiser\Workflow\Events\ModelTransited;

class ModelTransitedListener
{
    public function handle(ModelTransited $event)
    {
        if ($event->model instanceof Article) {
            $article = $event->model;

            if ($event->transition->target() === State::correction) {
                // Article was send to correction, the reason described in context
                $article->author->notify(
                    new ArticleHasProblem(
                        $article, $event->context['reason']
                    )
                );
            }
        }
    }
}
```

### Observer

Instead of using `EventListener` you may use an `Observer`.

First, you should add the trait, that adds the new events to the model.

```php
use \Codewiser\Workflow\Traits\WorkflowObserver;

class Article extaends Model
{
    use WorkflowObserver;
    
    protected $observables = ['transiting', 'transited'];
}
```

Now you may observe these events.

```php
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\StateMachineEngine;

class ArticleObserver
{
    public function transiting(
        Article $article, 
        StateMachineEngine $engine, 
        Transition $transition
    )
    {
        // return false to interrupt transition
    }

    public function transited(
        Article $article, 
        StateMachineEngine $engine, 
        Transition $transition, 
        array $context
    )
    {
        if ($transition->target() === State::correction) {
            $article->author->notify(
                new ArticleHasProblem(
                    $article, $context['reason']
                )
            );
        }
    }
}
```

### Transition Callback

Otherwise, you may define transition callback(s), that will be called after transition were successfully performed.

Callback is a `callable` with `Model` and `context` arguments.

```php
use \Codewiser\Workflow\Transition;

Transition::define(State::review, State::correction)
    ->after(function(Article $article, array $context) {
        $article->author->notify(
            new ArticleHasProblem(
                $article, $context['reason']
            )
        );
    }); 
```

You may define few callbacks to a single transition.
