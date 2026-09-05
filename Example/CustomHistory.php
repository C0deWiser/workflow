<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\Models\TransitionHistory;

class CustomHistory extends TransitionHistory
{
    protected $table = 'transition_history';

    public ?string $mark = null;
}