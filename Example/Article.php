<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\StateMachineEngine;
use Codewiser\Workflow\Traits\HasWorkflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Post
 * @package App
 *
 * @property string $body
 * @property string|null $state
 *
 * @property array $voices
 */
class Article extends Model
{
    use HasWorkflow;

    protected $attributes = [
        'state' => null
    ];

    public function state(): StateMachineEngine
    {
        return $this->workflow(ArticleWorkflow::class, 'state');
    }
}
