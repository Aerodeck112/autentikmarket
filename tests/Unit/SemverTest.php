<?php

namespace Tests\Unit;

use App\Support\Semver;
use PHPUnit\Framework\TestCase;

class SemverTest extends TestCase
{
    public function test_semver(): void
    {
        $this->assertTrue(Semver::isValid('1.4.2'));
        $this->assertFalse(Semver::isValid('1.4'));
        $this->assertFalse(Semver::isValid('01.4.2'));
        $this->assertTrue(Semver::gt('1.10.0', '1.9.9'));
        $this->assertTrue(Semver::gte('1.3.0', '1.3.0'));
        $this->assertTrue(Semver::satisfies('8.3.6', '>=8.3'));
        $this->assertFalse(Semver::satisfies('8.2.0', '>=8.3'));
        $this->assertTrue(Semver::satisfies('8.3.6', '>=8.3 <9'));
    }
}
