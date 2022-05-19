<?php


namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\Transition;
use Illuminate\Database\Eloquent\Model;

/**
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
     * @param null|StateMachineEngine $workflow workflow being transited
     * @param null|Transition $transition transition
     * @return mixed
     */
    public function fireTransitionEvent($event, $halt = true, $workflow = null, $transition = null)
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

        $payload = ['model' => $this, 'workflow' => $workflow, 'transition' => $transition];

        return !empty($result) ? $result : static::$dispatcher->{$method}(
            "eloquent.{$event}: ".static::class, $payload
        );
    }
}
