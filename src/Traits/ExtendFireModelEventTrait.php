<?php


namespace Codewiser\Workflow\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait ExtendFireModelEventTrait
 * @package Codewiser\Workflow\Traits
 * @mixin Model
 */
trait ExtendFireModelEventTrait
{
    /**
     * Fire the given event for the model.
     *
     * @param string $event
     * @param bool $halt
     *
     * @param null|string $workflowAttr name of workflow being updated
     * @param null|string $source previous state
     * @param null|string $target target state
     * @param array $payload any additional transition payload
     * @return mixed
     */
    public function fireTransitionEvent($event, $halt = true, $workflowAttr = null, $source = null, $target = null, $payload = [])
    {
        if (!isset(static::$dispatcher)) {
            return true;
        }

        // First, we will get the proper method to call on the event dispatcher, and then we
        // will attempt to fire a custom, object based event for the given event. If that
        // returns a result we can return that result, or we'll call the string events.
        $method = $halt ? 'until' : 'dispatch';

        $result = $this->filterModelEventResults(
            $this->fireCustomModelEvent($event, $method)
        );

        if (false === $result) {
            return false;
        }

        $payload = ['model' => $this, 'workflow' => $workflowAttr, 'source' => $source, 'target' => $target, 'payload' => $payload];

        return !empty($result) ? $result : static::$dispatcher->{$method}(
            "eloquent.{$event}: ".static::class, $payload
        );
    }
}