<?php

namespace Tests;

use Codewiser\Workflow\Example\Article;
use Codewiser\Workflow\Example\ArticleWorkflow;
use Codewiser\Workflow\Example\Enum;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Transition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function testNoParams()
    {
        $transition = Transition::make(Enum::new, Enum::review);
        $transition->inject(new StateMachine(new ArticleWorkflow, new Article, 'state'));

        // no exceptions
        $transition
            ->authorizedBy(fn() => true)
            ->authorize();

        $transition
            ->authorizedBy(fn() => Response::allow())
            ->authorize();

        $this->expectException(AuthorizationException::class);
        $transition
            ->authorizedBy(fn() => false)
            ->authorize();

        $this->expectException(AuthorizationException::class);
        $transition
            ->authorizedBy(fn() => Response::deny())
            ->authorize();

        $this->expectException(AuthorizationException::class);
        $transition
            ->authorizedBy(fn() => Response::deny()->authorize())
            ->authorize();
    }
}