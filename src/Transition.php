<?php


namespace Codewiser\Workflow;


use Codewiser\Workflow\Exceptions\WorkflowException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Transition
{
    protected $source;
    protected $target;
    protected $model;
    /**
     * @var Precondition
     */
    protected $precondition;
    public function __construct($source, $target, $precondition = null)
    {
        $this->source = $source;
        $this->target = $target;
        $this->precondition = $precondition;
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
     * @return Precondition
     */
    public function getPrecondition()
    {
        return $this->precondition;
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
        if ($precondition = $this->getPrecondition()) {
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