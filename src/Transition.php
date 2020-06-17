<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Transition between states in State Machine
 * @package Codewiser\Workflow
 */
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
     * @var Collection|callable[]
     */
    protected $preconditions;

    /**
     * These attributes must be provided into transit() method
     * @var Collection
     */
    protected $attributes;

    /**
     * Instantiate new transition
     * @param $source
     * @param $target
     * @return static
     */
    public static function define($source, $target)
    {
        return new static($source, $target);
    }

    public function __construct($source, $target)
    {
        $this->source = $source;
        $this->target = $target;
        $this->preconditions = new Collection();
        $this->attributes = new Collection();
    }

    /**
     * Add condition to the transition
     * @param callable $precondition
     * @return static
     */
    public function condition(callable $precondition)
    {
        $this->preconditions->push($precondition);
        return $this;
    }

    /**
     * Add requirement(s) to transition payload
     * @param string|string[] $attributes
     * @return static
     */
    public function requires($attributes)
    {
        if (is_string($attributes)) {
            $this->attributes->push($attributes);
        }
        if (is_array($attributes)) {
            $this->attributes->merge($attributes);
        }

        return $this;
    }

    public function toArray()
    {
        return [
            'caption' => $this->getCaption(),
            'source' => $this->getSource(),
            'target' => $this->getTarget(),
            'problem' => $this->hasProblem() ?: false,
            'requires' => $this->attributes->count() ? $this->attributes->toArray() : []
        ];
    }

    /**
     * Get human readable transition caption
     * @param bool $pastPerfect get caption for completed transition
     * @return array|Translator|string|null
     */
    public function getCaption($pastPerfect = false)
    {
        return trans(Str::snake(class_basename($this->workflow()->getBlueprint())) . "." . ($pastPerfect ? 'transited' : 'transitions') . ".{$this->getSource()}.{$this->getTarget()}");
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
     * @return Collection|callable[]
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
        try {
            $this->validate();
            return null;
        } catch (TransitionException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Get attributes, that must be provided into transit() method
     * @return Collection
     */
    public function getRequirements()
    {
        return $this->attributes;
    }

    /**
     * Parent context of this transition
     * @return StateMachineEngine|null
     */
    protected function workflow()
    {
        return $this->model->workflow($this->attribute);
    }

    /**
     * Examine transition preconditions
     * @throws TransitionRecoverableException
     * @throws TransitionFatalException
     */
    public function validate()
    {
        foreach ($this->getPreconditions() as $precondition) {
            if (is_callable($precondition)) {
                $precondition($this->model);
            } elseif (is_array($precondition) && is_object($precondition[0])) {
                $object = $precondition[0];
                $method = $precondition[1];
                $object->$method($this->model);
            }
        }
    }
}