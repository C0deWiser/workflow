<?php

namespace App;

use Codewiser\Workflow\Traits\HasWorkflow;
use Codewiser\Workflow\Traits\WorkflowObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Post
 * @package App
 *
 * @property string $body
 * @property State $state
 * @property string $next
 */
class Post extends Model
{
    use HasWorkflow;

    public $workflow = [
        'state' => Blueprint::class,
        'next' => Blueprint::class
    ];

    protected $casts = [
        'state' => State::class
    ];
}
