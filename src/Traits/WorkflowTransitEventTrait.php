<?php


namespace Codewiser\Workflow\Traits;


use Illuminate\Database\Eloquent\Model;

/**
 * Trait WorkflowTransitEventTrait
 * @package Codewiser\Workflow\Traits
 * @mixin Model
 */
trait WorkflowTransitEventTrait
{
    use ExtendFireModelEventTrait;

    public static function transiting($callback, $priority = 0)
    {
        static::registerModelEvent('transiting', $callback, $priority);
    }

    public static function transited($callback, $priority = 0)
    {
        static::registerModelEvent('transited', $callback, $priority);
    }
}