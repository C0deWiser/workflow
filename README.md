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
    'caption'   => 'Translateable string you may use as button caption',
    'source'    => 'Source state',
    'target'    => 'Target state',
    'problem'   => 'User can not perform transition while described problem not solved. See business-logic',
    'need_motivation' => 'User should provide comment to perform transition'
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

Then you dispatch `ModelTransited` event you may store user comment to the Model history.

With `MotivatedTransition` instead of `Transition` user comment is required.

### Preconditions

Additionally, `Transition` may has precondition. 
Precondition defines requirement for a model. If model fits the requirement the transition can be performed.

```php
// Without precondition
new Transition('review', 'published');

// One precondition
new Transition('new', 'review', new BodySizePrecondition());

// Few preconditions 
new Transition('correcting', 'review', [
    new BodySizePrecondition(),
    new DateTimePrecondition()
]); 
```

Here is the precondition, that requires an article to has body with at least 1000 symbols.

```php
class BodySizePrecondition extends \Codewiser\Workflow\Precondition
{
    public function validate($model, $attribute)
    {
        if (strlen($model->body) < 1000) {
            return 'Your Article should contain at least 1000 symbols';
        }
        
        // you may use $attribute to identify workflow schema
        $model->workflow($attribute);
    }
}
```

Now, if you try to change state of Article from `new` to `review` 
with Article body less then 1000 symbols, you will catch a `WorkflowException`.

```php
$transitions = $article->workflow()->getRelevantTransitions();

foreach ($transitions as $transition) {
    $targetState = $transition->getTarget();
    $problem = $transition->hasProblem(); 
    // If transition failed to meet the requiremetns 
    // we may show to the User $problem with description
}

```
