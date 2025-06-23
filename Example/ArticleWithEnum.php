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
 * @property Enum|null $state
 */
class ArticleWithEnum extends Model
{
    use HasWorkflow;

    public $casts = [
        'state' => Enum::class
    ];

    /**
     * @return StateMachineEngine<static, Enum>
     */
    public function state(): StateMachineEngine
    {
        return $this->workflow(ArticleEnumWorkflow::class, 'state');
    }
}
