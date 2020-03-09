# Workflow

Package provides workflow functionality to Models.

Workflow is a sequence of states, document evolve through. 
Transitions between states inflicts the evolution road.


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
    use Workflow;
    
    public function workflow(): WorkflowBlueprint
    {
        return new ArticleWorkflow($this);
    }
}
```

Workflow keeps its state in model attribute named `workflow` by default.
You should migrate model schema to add this column.

## Usage

Show to the User possible transitions from current state of the Article:

```php
$transitions = $article->workflow()->getRelevantTransitions();
```

User decides where to transit model. And you will update model with new `state` value.

If you try to update model with new `state` value, 
package will examine it, 
and you may catch a `WorkflowException`.

```php
try {
    // You may provide user comment for transition
    $article->workflow()->setTransitionComment('comment');
    $article->fill($request->all());
    $article->save();
} catch (\Codewiser\Workflow\Exceptions\WorkflowException $exception) {
    // Show to the user the reason he can't change Article state
    echo $exception->getMessage();
}
```

You may show to the User full history of transitions.

```php
/** @var \Codewiser\Journalism\Journal[] $history */
$history = $article->workflow()->journal()->limit(15)->get();
foreach ($history as $item) {
    $item->created_at;
    $item->event;
    $item->user;
    $item->payload;
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

    /**
     * Returns problem description or null if no there are no problems
     * @param Article $article
     * @return string|null
     */
    public function validate($article)
    {
        if (strlen($article->body) < 1000) {
            return 'Your Article should contain at least 1000 symbols';
        }
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

### Executing transition

When you change model `state`, it calls `execute` method of proper Transition.

By default this method just checks the preconditions and throws an Exception, if some requirements were not met.

```php
public function execute()
{
    if ($problem = $this->hasProblem()) {
        throw new WorkflowException($problem);
    }
}
```

You may override this method to perform more complex logic.

```php
public function execute()
{
    parent::execute();
    
    // For example
    // We need at least 3 votes from different users 
    // to complete the transition
    
    // Every attempt we count as a voice
    $voices->addVoice();

    if ($voices->count() < 3) {
        // revert model state
        $attrName = $this->model->workflow()->getAttributeName();
        $this->model->setAttribute(
            $attrName,
            $this->model->getOriginal($attrName)
        );
    }
}
```