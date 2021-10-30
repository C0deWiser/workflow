<?php

namespace Codewiser\Workflow\Exceptions;

use Throwable;

/**
 * Throws this exception to prevent transition from being in list of relevant transitions.
 *
 * @package Codewiser\Workflow\Exceptions
 */
class TransitionFatalException extends TransitionException
{
    public int $status = 403;

    public function __construct($message = "Transition is disabled", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}