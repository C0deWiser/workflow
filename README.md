# Workflow

Package provides workflow functionality to Models.

Workflow is a sequence of states, document evolve through. 
Transitions between states inflicts the evolution road.

## Installation

Follow instructions from `journalism` package.

## Setup

First, describe your model workflow.

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
    
    protected function stateMachine()
    {
        return [
            // do not add workflow attribute to $fillable !!!
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
    
    protected function stateMachine()
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

User decides where to transit model. And you will update model with new `state` value.

If you try to update model with new `state` value, 
package will examine it, 
and you may catch a `WorkflowException`.

`Workflow` trait provides scope.

```php

// Articles with new state
Article::query()->workflow('new');

// Articles with new state of editorial_workflow
Article::query()->workflow('new', 'editorial_workflow');
```

### Direct saving

You may call workflow methods to initialize or update workflow.

```php
class ArticleController extends Controller
{
    public function store(Request $request)
    {
        $article = new App\Article();
        $article->fill($request->all);
        // set workflow attribute to initial state
        $article->workflow()->init();
        $article->save();
    }

    public function update(Request $request, $id)
    {
        $article = App\Article::find($id);
        $article->fill($request->except('workflow'));
        // change workflow state 
        // `transition_comment` will be saved to journal
        $article->worflow()->transit(
            $request->get('workflow'), 
            $request->get('transition_comment')
        );
        $article->save();
    }
}
```

### Using observer

You may apply `WorkflowObserver` to your model. 
Then you do not need to call workflow methods.

The only thing — is to save transition comment.

```php
class ArticleController extends Controller
{
    public function store(Request $request)
    {
        $article = new App\Article();
        $article->fill($request->all);
                ->save();
        
        // `creating observer` will initialize every workflow
    }

    public function update(Request $request, $id)
    {
        $article = App\Article::find($id);
        // this will add user memo to the next journalized event
        $article->journalMemo($request->get('transition_comment'));
        $article->update($request->all());
    
        // `updating observer` will check 
        // state machine consitency and transition preconditions 
    }
}
```

### Journal

You may use `journal` package to store more than only transition history.
It may log every eloquent events. And even any user comments about model.

So you may craft user chat about every model.

```php
$article->journalMemo($request->get('comment'));
$article->journal('comment');
```

You may show full history of model updates, transitions and user conversations.

```php
foreach ($article->journal as $item) {
    $item->created_at;  // when
    $item->event;       // what
    $item->user;        // who
    $item->payload;     // model changeset
    $item->memo;        // user comment
    
    switch($item->event) {
        case 'created':
        case 'updated':
        case 'transited':
        case 'comment':
        // etc
    }
}

```

## Business Logic

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
