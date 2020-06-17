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

    /**
     * Get the observable event names.
     *
     * @return array
     */
    public function getObservableEvents()
    {
        return array_merge(
            parent::getObservableEvents(),
            [
                'transiting', 'transited'
            ],
            $this->observables
        );
    }

    public static function transiting($callback)
    {
        static::registerModelEvent('transiting', $callback);
    }

    public static function transited($callback)
    {
        static::registerModelEvent('transited', $callback);
    }
}