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
 * @property ExampleEnum|null $state
 * @property ExampleEnum|null $next
 */
class ExampleArticle extends Model
{
    use HasWorkflow;

    public $casts = [
        'state' => ExampleWorkflow::class,
        'next' => ExampleWorkflow::class
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
