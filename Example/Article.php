<?php

namespace Codewiser\Workflow\Example;

use Codewiser\Workflow\State;
use Codewiser\Workflow\Traits\HasWorkflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Post
 * @package App
 *
 * @property string $body
 * @property State|null $state
 * @property State|null $next
 */
class Article extends Model
{
    use HasWorkflow;

    public $casts = [
        'state' => ArticleWorkflow::class,
        'next' => ArticleWorkflow::class
    ];

    public function test1()
    {
        $this->state
            ->authorize('new')
            ->value = 'new';

        $this->state = 'new';
    }
    public function test2()
    {
        $this->state
            ->transitions()
            ->authorized()
            ->toArray();

    }
}
