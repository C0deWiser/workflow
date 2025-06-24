<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Database\Eloquent\Model;
use Codewiser\Workflow\StateMachineObserver;

/**
 * Watching for Eloquent events.
 */
trait HasWorkflow
{
    protected static function bootHasWorkflow(): void
    {
        static::creating(fn(Model $model) => (new StateMachineObserver)->creating($model));
        static::updating(fn(Model $model) => (new StateMachineObserver)->updating($model));
        static::created(fn(Model $model) => (new StateMachineObserver)->created($model));
        static::updated(fn(Model $model) => (new StateMachineObserver)->updated($model));
    }

    /**
     * Backdoor property to pass transition context to the StateMachineObserver.
     */
    public array $transition_context = [];

    protected array $state_machines = [];

    /**
     * @param  class-string<WorkflowBlueprint>|WorkflowBlueprint  $blueprint
     * @param  string  $attribute
     *
     * @return StateMachineEngine
     */
    protected function workflow(string|WorkflowBlueprint $blueprint, string $attribute): StateMachineEngine
    {
        if (!isset($this->state_machines[$attribute])) {

            $blueprint = $blueprint instanceof WorkflowBlueprint ?: new $blueprint;

            $this->state_machines[$attribute] = new StateMachineEngine($blueprint, $this, $attribute);
        }

        return $this->state_machines[$attribute];
    }
}
