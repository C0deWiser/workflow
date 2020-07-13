# Workflow

Package provides workflow functionality to Models.

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
            new Transition('new', 'review'),
            new Transition('review', 'published'),
            new Transition('review', 'correcting'),
            new Transition('correcting', 'review')
        ];
    }
}
```

The idea is that you can not change `state` attribute arbitrary.
The model always will be created with initial `state=new`.
Next value will be `review`. And so on.

Depending on `state` value you provide various business logic.
For example, one user creates article, 
second user reviews article 
and publish it or returns it back to the author.
First user corrects the mistakes and sends article for review again.

Second, apply workflow to your model.

```php
class Article extends Model
{
    use Codewiser\Workflow\Traits\Workflow;
    
    protected function workflowBlueprint()
    {
        return [
            'workflow' => ArticleWorkflow::class
        ];
    }
}
```

Workflow keeps its state in model attribute you provide as a key of array.
You should migrate model schema to add this column (string, not null).

You may define few workflow schemas as the same time, each in its own attribute.

```php
class Article extends Model
{
    use Workflow;
    
    protected function workflowBlueprint()
    {
        return [
            'editorial_workflow' => EditorialWorkflow::class,
            'technical_workflow' => TechnicalWorkflow::class,
        ];
    }
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

Now show to the User possible transitions from current state of the Article:

```php
$transitions = $article->workflow()->getRelevantTransitions();
```

You may convert transition to array.

```php
[
    'caption'   => string   // Translateable string you may use as button caption
    'source'    => string   // Source state
    'target'    => string   // Target state
    'problems'  => []       // User can not perform transition while described problems not solved. See business-logic
    'requires'  => []       // User should provide extra data to perform transition
]
```

User decides where to transit model. And you will update model with new `state` value.

If you try to update model with new `state` value, 
package will examine it, 
and you may catch a `WorkflowException`.

`Workflow` trait provides scope.

```php

// Articles with new state
Article::query()->onlyState('new');

// Articles with new state of editorial_workflow
Article::query()->onlyState('new', 'editorial_workflow');
```
### Changing state

Apply `StateMachineObserver` observer to the Model to keep model state machine consistency healthy.

```php
// creating: will set proper initial state
$article = new Article();
$article->save();

// transiting: will fire transition event
$article->workflow()->transit('review');
```

## Business Logic

### Transition extra data

Transition may require some additional information.

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
            Transition::define('review', 'correcting')->requires('reason'),
            Transition::define('correcting', 'review')
        ];
    }
}
```

User can not perform transition from `review` to `correcting` without providing information with name `reason`. 

```php
class ArticleController extends Controller
{
    public function update(Request $request, $id)
    {
        $target = $request->get('state');
        $reason = $request->get('reason');

        $article->worflow()->transit($target, ['reason' => $reason]);
    }
}
```

Generally, you may add any extra fields, even if it is not required, then performing transition:

```php
class ArticleController extends Controller
{
    public function update(Request $request, $id)
    {
        $target = $request->get('state');

        $article->worflow()->transit($target, $request->all());
    }
}
```

Do remember, that workflow model doesn't do anything with these additional information: 
you need to store it yourself by catching event, using observer or transition callback (read later).

Transition doesn't validate provided data. Validate it yourself.

### Conditions

Additionally, `Transition` may has condition. 
Condition defines requirements to a model. If model fits the requirement the transition can be performed.

Condition is a `callable` (see `call_user_func()`) with `Model` argument. Condition may throw an exception.
There are two types of transition exceptions — recoverable and not.

You may define few conditions to single transition.

#### Transitions with recoverable problems 

Throw `TransitionRecoverableException` if you suppose user to resolve the problem. Leave helping instructions in exception message. 
Here is an example of problem user may resolve.

```php
Transition::define('new', 'review')
    ->condition(function(Article $model) {
       if (strlen($model->body) < 1000) {
           throw new TransitionRecoverableException('Your Article should contain at least 1000 symbols');
       }
   });
```

User may see transition with problem in list of available transitions. 
User follows instructions to resolve a problem and tries to perform the transition again. 

#### Hiding transitions

In some cases workflow routes may divide into branches. What route to go decides business logic, not user.
So user even shouldn't know about other branch.

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

## Events

Transition generates `ModelTransited` event. You may define EventListener to detect it.

```php
class ModelTransited
{
    public function handle(ModelTransited $event)
    {
        if ($event->model instanceof Article) {
            $article = $event->model;

            if ($event->transition->getTarget() === 'correcting') {
                $article->author->notify(new ArticleHasProblem($article, $event->payload['reason']));
            }
        }
    }
}
```

## Observers

Instead of using Listener you may use Observer.

First, you should add trait to the model.

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
    public function transiting(Article $article, StateMachineEngine $workflow, Transition $transition, array $payload)
    {
        // return false to interrupt transition
    }

    public function transited(Article $article, StateMachineEngine $workflow, Transition $transition, array $payload)
    {
        if ($transition->getTarget() === 'correcting') {
            $article->author->notify(new ArticleHasProblem($article, $payload['reason']));
        }
    }
}
```

## Transition Callback

Otherwise you may define transition callback(s), that will be called after transition were successfully performed.

Callback is a `callable` (see `call_user_func()`) with `Model` and `payload` (extra data, provided by user) arguments.

You may define few callbacks to single transition.

```php
Transition::define('review', 'correcting')
    ->requires('reason')
    ->callback(function(Article $article, $payload) {
        $article->author->notify(new ArticleHasProblem($article, $payload['reason']));
    }); 
```