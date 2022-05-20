<?php

namespace Codewiser\Workflow\Example;

enum State
{
    case first;
    case second;
    case recoverable;
    case fatal;
    case callback;
    case deny;
}
