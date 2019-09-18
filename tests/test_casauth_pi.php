<?php

use PHPUnit\Framework\TestCase;

class PreauthorizeTest extends TestCase
{
    protected function setUp() {

    }

    public function testFailure() {
        $this->assertEmpty([1,2,3]);
    }
}