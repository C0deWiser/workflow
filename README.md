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
    'caption'   => string       // Translateable string you may use as button caption
    'source'    => string       // Source state
    'target'    => string       // Target state
    'problem'   => string|null  // User can not perform transition while described problem not solved. See business-logic
    'motivated' => boolean      // User should provide comment to perform transition
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

You may initiate or update state directly

```php
// creating
$article = new Article();
$article->state = 'new';
// or
$article->workflow()->init();
$article->save();

// updating
$article->state = 'review';
$article->save();
// or
$article->workflow()->transit('review'); // autosaving
```

You may apply `StateMachineObserver` observer to the Model, and model will be created with proper initial state.

```php
// creating
$article = new Article();
$article->save();
```

With `StateMachineProtector` observer on your Model, you may not change state attribute.
Then you should call workflow methods to update workflow.
This is useful if you need to set apart `update` and `transit` events.

```php
class ArticleController extends Controller
{
    public function update(Request $request, Article $article)
    {
        // update model properties except state attribute
        $article->fill($request->except('state'));
        // save model to fire update event
        $article->save();
        
        // change workflow state 
        // it saves model and fires transit event
        $article->worflow()->transit($request->get('state'));
    }
}
```

### Event

Observed transition generates `ModelTransited` event.

## Business Logic

### User comments

You may collect user comments about transitions.

```php
class ArticleController extends Controller
{
    public function update(Request $request, $id)
    {
        $article->worflow()->transit($request->get('state'), $request->get('comment'));
    }
}
```

You may dispatch `ModelTransited` event where you may store user comment to the Model history.

With `MotivatedTransition` instead of `Transition` user comment is required.

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
            // Reviewer must describe issue
            new MotivatedTransition('review', 'correcting'), 
            new Transition('correcting', 'review')
        ];
    }
}
```

### Conditions

Additionally, `Transition` may has condition. 
Condition defines requirements to a model. If model fits the requirement the transition can be performed.

Condition is a `callable` with `Model` argument. Condition may throw an exception.
There are two types of transition exceptions — recoverable and not.

#### Transitions with recoverable problems 

Throw `TransitionRecoverableException` if you suppose user to resolve the problem. Leave helping instructions in exception message. 
Here is an example of problem user may resolve.

```php
new Transition('new', 'review', function(Model $model) {
    if (strlen($model->body) < 1000) {
        throw new TransitionRecoverableException('Your Article should contain at least 1000 symbols');
    }
});
```

User may see transition with problem in list of available transitions. User follows instructions to resolve a problem and performs the transition then. 

#### Hiding transitions

In some cases workflow routes may divide into branches. What route to go decides business logic, not user.
So user even shouldn't know about other branch.

```php
new Transition('new', 'to-local-manager', function($model) {
    if ($model->orderAmount >= 1000000) {
        throw new TransitionFatalException();
    }
}); 
new Transition('new', 'to-region-manager', function($model) {
    if ($model->orderAmount < 1000000) {
        throw new TransitionFatalException();
    }
}); 
```

So user will see only one possible transition depending on order amount value.

#### Warning

You shouldn't use `Transition` classes without context. 
Access transitions only using `Model->workflow()->getTransitions()` or `Model->workflow()->getRelevantTransitions()`.
So transitions will have context and may be properly validated.