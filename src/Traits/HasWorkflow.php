<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\StateMachineEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Codewiser\Workflow\StateMachineObserver;

/**
 * Trait adds Workflow to a Model.
 *
 * @mixin Model
 * @property array $workflow
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

    /**
     * Engine instances.
     */
    protected array $stateMachineEngines = [];

    /**
     * Get model's workflow (for given attribute or first defined).
     */
    public function workflow(string $what = null): ?StateMachineEngine
    {
        if (isset($this->workflow)) {
            foreach ((array)$this->workflow as $attr => $class) {
                if (!$what || $class == $what || $attr == $what) {

                    if (isset($this->stateMachineEngines[$attr])) {
                        return $this->stateMachineEngines[$attr];
                    }

                    $this->stateMachineEngines[$attr] = new StateMachineEngine(new $class(), $this, $attr);

                    return $this->stateMachineEngines[$attr];
                }
            }
        }
        return null;
    }

    /**
     * Get the model workflow listing.
     *
     * @return Collection<StateMachineEngine>
     */
    public function getWorkflowListing(): Collection
    {
        $list = collect();
        if (isset($this->workflow)) {
            foreach (array_keys((array)$this->workflow) as $workflow) {
                $list->push($this->workflow($workflow));
            }
        }
        return $list;
    }
}
