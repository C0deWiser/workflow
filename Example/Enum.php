<?php

namespace Codewiser\Workflow\Example;

enum Enum: string
{
    case new = 'first';
    case review = 'second';
    case published = 'recoverable';
    case correction = 'fatal';
    case empty = 'empty';
}
