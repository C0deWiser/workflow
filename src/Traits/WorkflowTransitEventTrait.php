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

    public static function transiting($callback)
    {
        static::registerModelEvent('transiting', $callback);
    }

    public static function transited($callback)
    {
        static::registerModelEvent('transited', $callback);
    }
}