# Workflow

Package provides workflow functionality to Eloquent Models.

Workflow is a sequence of states, document evolve through. 
Transitions between states inflicts the evolution road.

## Setup

First, describe your model workflow blueprint.

```php
class ArticleWorkflow extends \Codewiser\Workflow\WorkflowBlueprint
{
    protected function states(): array
    {
        return ['new', 'review', 'published', 'correcting'];
    }
    protected function transitions(): array
    {
        return [
            Transition::define('new', 'review'),
            Transition::define('review', 'published'),
            Transition::define('review', 'correcting'),
            Transition::define('correcting', 'review')
        ];
    }
}
```

Second, apply workflow to your model.

```php
class Article extends Model
{
    use Codewiser\Workflow\Traits\Workflow;
    
    public $workflow = [
        'state' => ArticleWorkflow::class
    ];
}
```

Workflow keeps its state in model attribute you provide as a key of array.
You should migrate model schema to add this column (string, not null).

You may define few workflow schemas as the same time, each in its own attribute.

```php
class Article extends Model
{
    use Workflow;
    
    public $workflow = [
        'editorial_workflow' => EditorialWorkflow::class,
        'technical_workflow' => TechnicalWorkflow::class,
    ];
}
```

## Usage

You may access workflow service class through the model.

```php

$article = Article::find(1);

// Will return first defined workflow
$article->workflow(); 

// Will return workflow binded to `editorial_workflow` attribute
$article->workflow('editorial_workflow'); 

// Will return EditorialWorkflow
$article->workflow(EditorialWorkflow::class); 

```

So, if your model has few workflow schemas, you may get the exact you need. 

Now show to the user possible transitions from the current state of the article:

```php
public function show(Article $article) 
{
    $data = $article->toArray();
    
    $data['state'] => $article->workflow()->caption();
    $data['transitions'] = $article->workflow()->channels()->toArray();
    
    return response()->json($data);
}
```

Captions of current state and transitions are translatable string. 

```json
{
  "id": 1,
  "title": "Article title",
  "state": "blueprint.states.draft",
  "transitions": [
    {
      "caption": "blueprint.transitions.draft.publish",
      "source": "draft",
      "target": "publish",
      "problems": [],
      "requires": []
    }
  ]
}
```

User decides where to transit model. And you will update model with new `state` value.

### Changing state

Workflow observes Model and keeps model state machine consistency healthy.

```php
// creating: will set proper initial state
$article = new Article();
$article->save();

// updating: will examine state machine consistency
$article->state = 'review';
$article->save();
```

Saving with a wrong state will be caught by `updating` observer.

## Authorization

You may check authorization of users, trying to perform transition.

You may limit transition listing with only authorized items:

```php
$transitions = $article->workflow()->channels()->authorized();
```

Then authorize transition in controller before applying changes:

```php
public function update(Request $request, Article $article)
{
    $this->authorize('update', $article);
    
    if ($state = $request->get('state')) {
        $article->workflow()->authorize($state);
        $article->state = $state;
    }
    
    $article->save();
}
```

Transition may be authorized with policy or with `Closure` callback. 

### Using Policy

```php
Transition::define('new', 'review')
    ->authorize('toReview');
```

```php
class ArticlePolicy 
{
    public function toReview(User $user, Article $article)
    {
        return $article->author->is($user);
    }
}
```

### Using Closure

```php
Transition::define('new', 'review')
    ->authorize(function (Article $article) {
        return $article->author->is(auth()->user());
    });
```

## Business Logic

### Conditions

Additionally, transition may have a conditions. 
Condition defines requirements to a model. If model fits the requirement the transition can be performed.

Condition is a `Closure` with `Model` argument. Condition may throw an exception.
There are two types of transition exceptions — recoverable and fatal.

You may define few conditions to a single transition.

#### Transitions with recoverable problems 

Throw `TransitionRecoverableException` if you suppose user to resolve the problem. Leave helping instructions in exception message. 

Here is an example of problem user may resolve.

```php
Transition::define('new', 'review')
    ->condition(function(Article $model) {
       if (strlen($model->body) < 1000) {
           throw new TransitionRecoverableException('Your article should contain at least 1000 symbols');
       }
   });
```

User will see transition with problem in list of available transitions. 
User follows instructions to resolve the problem and tries to perform the transition again.
You should disable problem transition in user interface and show to the user problem description.

```json
{
  "id": 1,
  "title": "Article title",
  "state": "blueprint.states.draft",
  "transitions": [
    {
      "caption": "blueprint.transitions.draft.publish",
      "source": "draft",
      "target": "publish",
      "problems": [
        "Your article should contain at least 1000 symbols"
      ],
      "requires": []
    }
  ]
}
```

#### Hiding transitions

In some cases workflow routes may divide into branches. What route to go decides business logic, not user.
So user even shouldn't know about other branches.

```php
Transition::define('new', 'to-local-manager')
    ->condition(function($model) {
        if ($model->orderAmount >= 1000000) {
            throw new TransitionFatalException();
        }
    }); 

Transition::define('new', 'to-region-manager')
    ->condition(function($model) {
        if ($model->orderAmount < 1000000) {
            throw new TransitionFatalException();
        }
    }); 
```

So user will see only one possible transition depending on order amount value.

## Additional context

Sometimes you need to get additional context to perform a transition. For example, it may be a reason the article was rejected by the reviewer.

First, declare the requirements in transition definition:

```php
Transition::define('review', 'reject')
    ->requires('reason');
```

The transition will look like:

```json
{
  "id": 1,
  "title": "Article title",
  "state": "blueprint.states.review",
  "transitions": [
    {
      "caption": "blueprint.transitions.review.reject",
      "source": "review",
      "target": "reject",
      "problems": [],
      "requires": ["reason"]
    }
  ]
}
```

Next, provide the context in the controller:

```php
public function update(Request $request, Article $article)
{
    $this->authorize('update', $article);
    
    if ($state = $request->get('state')) {
        $article->workflow()->authorize($state);
        $article->state = $state;
        
        $article->workflow()->context($request->all());
    }
    
    $article->save();
}
```

The context will be validated while saving, and you may receive the `ValidationException`.

After all you may use this context in events (see below).

## Events

Transition generates `ModelTransited` event. You may define `EventListener` to listen to it.

```php
class ModelTransited
{
    public function handle(ModelTransited $event)
    {
        if ($event->model instanceof Article) {
            $article = $event->model;

            if ($event->transition->target() === 'correcting') {
                $article->author->notify(new ArticleHasProblem($article, $event->transition->context()));
            }
        }
    }
}
```

## Observers

Instead of using `Listener` you may use `Observer`.

First, you should add the trait, that adds the new events to the model.

```php
class Article 
{
    use WorkflowObserver;
    protected $observables = ['transiting', 'transited'];
}
```

Now you may observe these events.

```php
class ArticleObserver
{
    public function transiting(Article $article, StateMachineEngine $engine, Transition $transition)
    {
        // return false to interrupt transition
    }

    public function transited(Article $article, StateMachineEngine $engine, Transition $transition)
    {
        if ($transition->target() === 'correcting') {
            $article->author->notify(new ArticleHasProblem($article, $transition->context()));
        }
    }
}
```

## Transition Callback

Otherwise, you may define transition callback(s), that will be called after transition were successfully performed.

Callback is a `Closure` with `Model` argument.

```php
Transition::define('review', 'correcting')
    ->callback(function(Article $article, array $context) {
        $article->author->notify(new ArticleHasProblem($article, $context));
    }); 
```

You may define few callbacks to a single transition.