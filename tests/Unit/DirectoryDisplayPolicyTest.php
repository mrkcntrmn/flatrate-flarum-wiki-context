<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Support\DirectoryDisplayPolicy;
use PHPUnit\Framework\TestCase;

final class DirectoryDisplayPolicyTest extends TestCase
{
    public function test_directory_caps(): void
    {
        $c = DirectoryDisplayPolicy::contract();
        $this->assertTrue($c['direct_children_only']);
        $this->assertSame(8, $c['desktop']['initial']);
        $this->assertSame(24, $c['desktop']['inline_max']);
        $this->assertSame(6, $c['mobile']['initial']);
        $this->assertSame(18, $c['mobile']['inline_max']);
        $this->assertSame(1, $c['catch_all']['max_active_per_parent']);
        $this->assertTrue($c['catch_all']['overflow_does_not_use_misc']);
        $digest = DirectoryDisplayPolicy::digest();
        $this->assertSame($digest, DirectoryDisplayPolicy::digest());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $digest);
    }
}
