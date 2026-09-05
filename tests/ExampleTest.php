<?php

namespace Tests;

use Codewiser\Workflow\Example\FakedValidator;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testFakeValidator()
    {
        $v = new FakedValidator(['name' => 'Foo'], ['name' => 'required']);
        $this->assertFalse($v->fails());
        $this->assertEquals(['name' => 'Foo'], $v->validate());

        $v = new FakedValidator([], ['name' => 'nullable']);
        $this->assertFalse($v->fails());
        $this->assertEquals([], $v->validate());

        $v = new FakedValidator(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertEquals(['name' => ''], $v->failed());
    }
}