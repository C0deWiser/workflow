<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Exceptions\WorkflowInvalidTransitionException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Transition implements Arrayable
{
    protected $source;
    protected $target;
    /**
     * @var Model|Workflow
     */
    protected $model;
    /**
     * @var string
     */
    protected $attribute;
    /**
     * @var Collection|Precondition[]
     */
    protected $preconditions;
    public function __construct($source, $target, $precondition = null)
    {
        $this->source = $source;
        $this->target = $target;
        $this->preconditions = new Collection();
        if ($precondition) {
            $this->preconditions->push($precondition);
        }
    }

    public function toArray()
    {
        return [
            'source' => $this->getSource(),
            'target' => $this->getTarget(),
            'problem' => $this->hasProblem()
        ];
    }

    /**
     * Source state
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Target state
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Precondition
     * @return Collection|Precondition[]
     */
    public function getPreconditions()
    {
        return $this->preconditions;
    }

    public function __call($name, $arguments)
    {
        switch ($name) {
            case 'inject':
                $this->model = $arguments[0];
                $this->attribute = $arguments[1];
                break;
        }
    }

    /**
     * Returns reason (if some) the transition can not be executed
     * @return string|null
     */
    public function hasProblem()
    {
        if ($this->model) {
            foreach ($this->getPreconditions() as $precondition) {
                if ($problem = $precondition->validate($this->model, $this->attribute)) {
                    return $problem;
                }
            }
        }
    }

    /**
     * Parent context of this transition
     * @return WorkflowBlueprint|null
     */
    protected function workflow()
    {
        return $this->model->workflow($this->attribute);
    }

    /**
     * Execute transition: check preconditions
     * @throws WorkflowInvalidTransitionException
     */
    public function validate()
    {
        if ($problem = $this->hasProblem()) {
            throw new WorkflowInvalidTransitionException($problem);
        }
    }

}