<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Attributes\Workflow;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Traits\HasTransitionHistory;
use Codewiser\Workflow\Traits\HasWorkflow;
use Codewiser\Workflow\WorkflowObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @property string $body
 * @property Enum $state
 *
 * @property Collection $votes
 *
 * @property bool $condition
 * @property string $auth_token
 */
#[ObservedBy(WorkflowObserver::class)]
class Article extends Model
{
    use HasWorkflow, HasTransitionHistory;

    protected $attributes = [
        // For test purposes
        'state' => null,
        'votes' => '[]'
    ];

    protected function casts(): array
    {
        return [
            'state'     => Enum::class,
            'condition' => 'boolean',
            'votes'     => AsCollection::class,
        ];
    }

    /**
     * @return StateMachine<self, Enum>
     */
    #[Workflow]
    public function state(): StateMachine
    {
        return $this->workflow(ArticleWorkflow::class, __METHOD__);
    }

    public function state1(): StateMachine
    {
        return $this->workflow(ArticleWorkflow::class, 'state1');
    }

    public function states(string $attribute): StateMachine
    {
        return $attribute === 'state'
            ? $this->state()
            : $this->state1();
    }
}
