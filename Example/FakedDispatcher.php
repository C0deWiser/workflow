<?php

namespace Codewiser\Workflow\Example;

use Illuminate\Contracts\Events\Dispatcher;

class FakedDispatcher implements Dispatcher
{
    public array $dispatched = [];

    public function listen($events, $listener = null)
    {
        //
    }

    public function hasListeners($eventName)
    {
        return false;
    }

    public function subscribe($subscriber)
    {
        //
    }

    public function until($event, $payload = [])
    {
        //
    }

    public function dispatch($event, $payload = [], $halt = false)
    {
        $this->dispatched[] = $event;

        return null;
    }

    public function push($event, $payload = [])
    {
        //
    }

    public function flush($event)
    {
        //
    }

    public function forget($event)
    {
        //
    }

    public function forgetPushed()
    {
        //
    }
}