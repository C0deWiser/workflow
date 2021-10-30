<?php


namespace Codewiser\Workflow\Exceptions;

use Illuminate\Support\MessageBag;
use Throwable;

/**
 * User may resolve issues with transition (left instructions in the message).
 *
 * @package Codewiser\Workflow\Exceptions
 */
class TransitionRecoverableException extends TransitionException
{
    public int $status = 422;

    public function __construct($message = 'Transition is disabled', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}