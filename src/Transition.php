<?php


namespace Codewiser\Workflow;


use Codewiser\Workflow\Exceptions\WorkflowException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Transition
{
    protected $source;
    protected $target;
    /**
     * @var Model
     */
    protected $model;
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
                break;
        }
    }

    /**
     * Returns reason (if some) the transition can not be executed
     * @return string|null
     */
    public function hasProblem()
    {
        foreach ($this->getPreconditions() as $precondition) {
            if ($problem = $precondition->validate($this->model)) {
                return $problem;
            }
        }
    }

    /**
     * Execute transition: check preconditions
     * @throws WorkflowException
     */
    public function execute()
    {
        if ($problem = $this->hasProblem()) {
            throw new WorkflowException($problem);
        }
    }

}