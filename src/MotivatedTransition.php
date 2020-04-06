<?php


namespace Codewiser\Workflow;


/**
 * Transition requires user comment
 * @package Codewiser\Workflow
 */
class MotivatedTransition extends Transition
{

    public function toArray()
    {
        $data = parent::toArray();
        $data['need_motivation'] = true;
        return $data;
    }
}