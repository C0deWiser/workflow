<?php

namespace Codewiser\Workflow\Example;

enum Enum: string
{
    case new = 'new';
    case review = 'review';
    case published = 'published';
    case correction = 'correction';
    case unreacheable = 'empty';
    case chargeable = 'cumulative';
    case prohibited = 'prohibited';
}
