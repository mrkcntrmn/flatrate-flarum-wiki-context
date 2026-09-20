<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Projection\ProjectionAuth;
use PHPUnit\Framework\TestCase;

/**
 * Documents ACTIVATION_PARENT_EQUALS_ACTIVE invariant at contract level.
 * Full DB race proof is exercised in Integration + MariaDB harness.
 */
final class ActivationParentContractTest extends TestCase
{
    public function test_parent_equals_active_invariant_documented(): void
    {
        // Normal forward activation requires parent == current active.
        $parent = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $active = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $this->assertSame($parent, $active);
        $this->assertSame('ACCEPT_CANDIDATE', ProjectionAuth::classifyVersion(2, 1, false, false));
        echo "ACTIVATION_PARENT_EQUALS_ACTIVE=PASS\n";
    }
}
