<?php


namespace Codewiser\Workflow\Exceptions;

use Throwable;

/**
 * User may resolve issues with transition (left instructions in the message)
 * @package Codewiser\Workflow\Exceptions
 */
class TransitionRecoverableException extends TransitionException
{
public function __construct($message, $code = 0, Throwable $previous = null)
{
    parent::__construct($message, $code, $previous);
}
}