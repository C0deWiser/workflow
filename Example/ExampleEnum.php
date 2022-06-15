<?php

namespace Codewiser\Workflow\Example;

enum ExampleEnum
{
    case first;
    case second;
    case recoverable;
    case fatal;
    case callback;
    case deny;
}
