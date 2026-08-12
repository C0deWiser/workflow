<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Contracts\Workflow;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\WorkflowObserver;
use Codewiser\Workflow\Traits\HasTransitionHistory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $body
 * @property Enum $state
 *
 * @property array $voices
 */
#[ObservedBy(WorkflowObserver::class)]
class Article extends Model implements Workflow
{
    use HasTransitionHistory;

    protected $attributes = [
        // For test purposes
        'state' => null
    ];

    protected function casts(): array
    {
        return[
            'state' => Enum::class
        ];
    }

    public function blueprints(): array
    {
        $engine = $this->state();

        return [
            $engine->attribute => $engine->blueprint
        ];
    }

    /**
     * @return StateMachine<Article, Enum>
     */
    public function state(): StateMachine
    {
        return new StateMachine(new ArticleWorkflow(), $this, 'state');
    }
}
