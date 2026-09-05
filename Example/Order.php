<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Attributes\Workflow;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Traits\HasWorkflow;
use Codewiser\Workflow\WorkflowObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Enum $status
 */
#[ObservedBy(WorkflowObserver::class)]
class Order extends Model
{
    use HasWorkflow;

    protected $attributes = [
        // For test purposes
        'status' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => Enum::class,
        ];
    }

    /**
     * State is stored in the 'status' attribute, not in the method name.
     *
     * @return StateMachine<self, Enum>
     */
    #[Workflow('status')]
    public function lifecycle(): StateMachine
    {
        return $this->workflow(ArticleWorkflow::class, 'status');
    }
}