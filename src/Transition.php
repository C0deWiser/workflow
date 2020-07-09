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
    protected $conditions;
    /**
     * @var Collection|callable[]
     */
    protected $callbacks;

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
        $this->conditions = new Collection();
        $this->attributes = new Collection();
        $this->callbacks = new Collection();
    }

    /**
     * Add condition to the transition
     * @param callable $condition
     * @return static
     */
    public function condition(callable $condition)
    {
        $this->conditions->push($condition);
        return $this;
    }

    /**
     * Callback(s) will run after transition is done
     * @param callable $callback
     * @return $this
     */
    public function callback(callable $callback)
    {
        $this->callbacks->push($callback);
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
     * Get registered preconditions
     * @return Collection|callable[]
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Get registered transition callbacks
     * @return callable[]|Collection
     */
    public function getCallbacks()
    {
        return $this->callbacks;
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
        foreach ($this->getConditions() as $condition) {
            if (is_callable($condition)) {
                $condition($this->model);
            } elseif (is_array($condition) && is_object($condition[0])) {
                $object = $condition[0];
                $method = $condition[1];
                $object->$method($this->model);
            }
        }
    }
}