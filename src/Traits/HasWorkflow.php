<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Codewiser\Workflow\StateMachineObserver;

/**
 * Watching for Eloquent events.
 */
trait HasWorkflow
{
    protected static function bootHasWorkflow()
    {
        static::creating(function (Model $model) {
            return (new StateMachineObserver)->creating($model);
        });

        static::created(function (Model $model) {
            (new StateMachineObserver)->created($model);
        });

        static::updating(function (Model $model) {
            return (new StateMachineObserver)->updating($model);
        });

        static::updated(function (Model $model) {
            (new StateMachineObserver)->updated($model);
        });
    }

    public array $state_machines = [];

    protected function workflow(string|WorkflowBlueprint $blueprint, string $on): StateMachineEngine
    {
        if (!isset($this->state_machines[$on])) {

            $blueprint = $blueprint instanceof WorkflowBlueprint ?: new $blueprint;

            $this->state_machines[$on] = new StateMachineEngine($blueprint, $this, $on);
        }

        return $this->state_machines[$on];
    }
}
