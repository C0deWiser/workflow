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

First, describe the workflow blueprint with available states and transitions:

```php
class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    public function states(): array
    {
        return [
            'new',
            'review',
            'published',
            'correction',
        ];
    }
    
    public function transitions(): array
    {
        return [
            ['new', 'review'],
            ['review', 'published'],
            ['review', 'correction'],
            ['correction', 'review']
        ];
    }
}
```

You may use `Enum` instead of scalar values:

```php
class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    public function states(): array
    {
        return State::cases();
    }
    
    public function transitions(): array
    {
        return [
            [State::new, State::review],
            [State::review, State::published],
            [State::review, State::correction],
            [State::correction, State::review]
        ];
    }
}
```

Finally, include trait and cast attribute with a blueprint that you just create.

```php
class Article extends Model
{
    use \Codewiser\Workflow\Traits\HasWorkflow;
    
    public $casts = [
        'state' => ArticleWorkflow::class
    ];
}
```

That's it.

## Consistency

Workflow observes Model and keeps state machine consistency healthy.

```php
// creating: will set proper initial state
$article = new \Codewiser\Workflow\Example\Article();
$article->save();
assert($article->state->value == 'new');

// updating: will examine state machine consistency
$article->state = 'review';
$article->save();
// No exceptions thrown
```


## State and Transition objects

In an example above we describe blueprint with scalar or enum values, but actually they will be transformed to the objects. Those objects bring some additional functionality to the states and transitions, such as caption translations, transition authorization, routing rules etc...

```php
use \Codewiser\Workflow\State;
use \Codewiser\Workflow\Transition;

class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    public function states(): array
    {
        return [
            State::make('new'),
            State::make('review'),
            State::make('published'),
            State::make('correction'),
        ];
    }
    
    public function transitions(): array
    {
        return [
            Transition::make('new', 'review'),
            Transition::make('review', 'published'),
            Transition::make('review', 'correction'),
            Transition::make('correction', 'review')
        ];
    }
}
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

Transition::make('new', 'review')
    ->authorizedBy('transitToReview');
```

### Using Closure

```php
use \Codewiser\Workflow\Transition;
use \Illuminate\Support\Facades\Gate;

Transition::make('new', 'review')
    ->authorizedBy(fn(Article $article) => Gate::allows('transitToReview', $article));
```

### Authorized Transitions

To get only transitions, authorized to the current user, use `authorized` method of `TransitionCollection`:

```php
$article = new \Codewiser\Workflow\Example\Article();

$transitions = $article->state
    // Get transitions from model's current state.
    ->transitions()
    // Filter only authorized transitions. 
    ->authorized();
```

### Authorizing Transition

When accepting user request, do not forget to authorize workflow state changing.

```php
public function update(Request $request, \Codewiser\Workflow\Example\Article $article)
{
    $this->authorize('update', $article);
    
    if ($state = $request->get('state')) {
        // Check if user allowed to make this transition
        $article->state->authorize($state);
    }
    
    $article->fill($request->validated());
    
    $article->save();
}
```

## Business Logic

### Disabling transitions

Transition may have some prerequisites to a model. If model fits this conditions then the transition can be performed.

Prerequisite is a `callable` with `Model` argument. It may throw an exception.

To disable transition, prerequisite should throw a `TransitionRecoverableException`. Leave helping instructions in
exception message.

Here is an example of issues user may resolve.

```php
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\Exceptions\TransitionRecoverableException;

Transition::make('new', 'review')
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
User follows instructions to resolve the issue and then may try to perform the transition again.

### Removing transitions

In some cases workflow routes may divide into branches. Way to go forced by business logic, not user.
User even shouldn't know about other ways.

To completely remove transition from a list, prerequisite should throw a `TransitionFatalException`.

```php
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\Exceptions\TransitionFatalException;

Transition::make('new', 'to-local-manager')
    ->before(function(Order $model) {
        if ($model->amount >= 1000000) {
            throw new TransitionFatalException("Order amount is too big for this transition.");
        }
    }); 

Transition::make('new', 'to-region-manager')
    ->before(function(Order $model) {
        if ($model->amount < 1000000) {
            throw new TransitionFatalException("Order amount is too small for this transition.");
        }
    }); 
```

User will see only one possible transition depending on order amount value.

### Additional Context

Sometimes application requires an additional context to perform a transition. For example, it may be a reason the article was rejected by the reviewer.

First, declare validation rules in transition definition:

```php
use \Codewiser\Workflow\Transition;

Transition::make('review', 'reject')
    ->rules([
        'reason' => 'required|string|min:100'
    ]);
```

Next, set the transition context in the controller:

```php
use Illuminate\Http\Request;

public function update(Request $request, \Codewiser\Workflow\Example\Article $article)
{
    $this->authorize('update', $article);
    
    if ($state = $request->get('state')) {
        $article->state
            // Authorize transition
            ->authorize($state)
            // Put transition context
            ->context($request->all());

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
use \Codewiser\Workflow\State;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{
    protected function states(): array
    {
        return [
            State::make('new')->as(__('Draft')),
            State::make('published')->as(__('Published'))
        ];
    }
    protected function transitions(): array
    {
        return [
            Transition::make('new', 'published')->as('Publish')
        ];
    }
}
```

## Additional Attributes

Sometimes we need to add some additional attributes to the workflow states and transitions. For example, we may group states by levels and use this information to color states and transitions in user interface.

```php
use \Codewiser\Workflow\State;
use \Codewiser\Workflow\Transition;
use \Codewiser\Workflow\WorkflowBlueprint;

class ArticleWorkflow extends WorkflowBlueprint
{
    protected function states(): array
    {
        return [
            State::make('new'),
            State::make('review')     ->set('level', 'warning'),
            State::make('published')  ->set('level', 'success'),
            State::make('correction') ->set('level', 'danger')
        ];
    }
    protected function transitions(): array
    {
        return [
            Transition::make('new', 'review')         ->set('level', 'warning'),
            Transition::make('review', 'published')   ->set('level', 'success'),
            Transition::make('review', 'correction')  ->set('level', 'danger'),
            Transition::make('correction', 'review')  ->set('level', 'warning')
        ];
    }
}
```

## Json Serialization

For user to interact with model's workflow we should pass the data to a frontend of the application:

```php
use Illuminate\Http\Request;

public function show(\Codewiser\Workflow\Example\Article $article)
{
    $this->authorize('view', $article);
    
    $data = $article->toArray();
    
    $data['state']['transitions'] = $article->state->transitions()->authorized()->toArray();
    
    return $data;
}
```

The payload will be like that:

```json
{
  "id": 1,
  "title": "Article title",
  "state": {
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
        "rules": [],
        "level": "success"
      },
      {
        "source": "review",
        "target": "correction",
        "name": "Send to Correction",
        "issues": [],
        "rules": {
          "reason": ["required", "string", "min:100"]
        },
        "level": "danger"
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

            if ($event->transition->target()->is('correction')) {
                // Article was send to correction, the reason described in context
                $article->author->notify(
                    new ArticleHasProblem(
                        $article, $event->transition->context('reason')
                    )
                );
            }
        }
    }
}
```

### Transition Callback

Otherwise, you may define transition callback(s), that will be called after transition were successfully performed.

Callback is a `callable` with `Model` and `context` arguments.

```php
use \Codewiser\Workflow\Transition;

Transition::make('review', 'correcting')
    ->after(function(Article $article, array $context) {
        $article->author->notify(
            new ArticleHasProblem(
                $article, $context['reason']
            )
        );
    }); 
```

You may define few callbacks to a single transition.
