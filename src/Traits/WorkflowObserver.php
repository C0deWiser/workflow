<?php


namespace Codewiser\Workflow\Traits;


use Illuminate\Database\Eloquent\Model;

/**
 * Trait adds Transition Event to a Model.
 *
 * @mixin Model
 */
trait WorkflowObserver
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
