<?php

namespace App;

enum State: string
{
    case one = 'one';
    case recoverable = 'recoverable';
    case fatal = 'fatal';
    case callback = 'callback';
    case deny = 'deny';

    public function caption(): string
    {
        return $this->name;
    }

    public function attributes(): array
    {
        return [];
    }
}
