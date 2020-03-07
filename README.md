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

Show to the User possible transitions from current state of Article:

```php
$transitions = $article->workflow()->getRelevantTransitions();
```

User decides where to transit model. And you will update model with new `state` value.

If you try to update model with new `state` value, 
package will examine it, 
and you may catch a `WorkflowException`.

## Business Logic

Additionally, `Transition` may has precondition. 

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

```php
new Transition('new', 'review', new BodySizePrecondition())
```

If you try to change state of Article from `new` to `review` 
with Article body less then 1000 symbols, you will catch a `WorkflowException`.