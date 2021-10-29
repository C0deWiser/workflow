<?php

namespace App;

use Codewiser\Workflow\Traits\Workflow;
use Codewiser\Workflow\Traits\WorkflowObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Post
 * @package App
 *
 * @property string $body
 * @property string $state
 * @property string $next
 */
class Post extends Model
{
    use Workflow;

    public $workflow = [
        'state' => Blueprint::class,
        'next' => Blueprint::class
    ];
}