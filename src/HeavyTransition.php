<?php


namespace Codewiser\Workflow;

/**
 * Transition requires additional information from actor
 * @package Codewiser\Workflow
 */
class HeavyTransition extends Transition
{
    /**
     * These attributes must be provided into transit() method
     * @var array
     */
    protected $attributes;

    public function __construct($source, $target, array $attributes, $precondition = null)
    {
        parent::__construct($source, $target, $precondition);
        $this->attributes = $attributes;
    }

    public function toArray()
    {
        return parent::toArray() + ['requires' => $this->attributes];
    }

    /**
     * Get attributes, that must be provided into transit() method
     * @return array
     */
    public function getRequiredAttributes(): array
    {
        return $this->attributes;
    }
}