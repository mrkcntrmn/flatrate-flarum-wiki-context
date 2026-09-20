<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Support\FeatureGates;
use PHPUnit\Framework\TestCase;

final class FeatureGatesTest extends TestCase
{
    public function test_all_gates_default_closed(): void
    {
        $map = FeatureGates::defaultClosedMap();
        $this->assertFalse($map[FeatureGates::PROJECTION_SYNC_ENABLED]);
        $this->assertFalse($map[FeatureGates::BROWSE_ROUTES_ENABLED]);
        $this->assertFalse($map[FeatureGates::CONTEXT_WRITES_ENABLED]);
        $this->assertFalse($map[FeatureGates::DERIVED_FEEDS_ENABLED]);
        $this->assertFalse($map[FeatureGates::BRAND_ROOT_DERIVED_FEEDS_ENABLED]);
        $this->assertFalse($map[FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED]);
        $this->assertFalse($map[FeatureGates::PUBLIC_ROLLOUT_ENABLED]);
        echo "FEATURE_GATES_DEFAULT_CLOSED=PASS\n";
        echo "ADMIN_GHOST_PREVIEW_DEFAULT_CLOSED=PASS\n";
        echo "PUBLIC_ROLLOUT_DEFAULT_CLOSED=PASS\n";
    }
}
