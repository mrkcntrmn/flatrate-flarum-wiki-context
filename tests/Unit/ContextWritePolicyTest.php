<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Context\ContextWritePolicy;
use PHPUnit\Framework\TestCase;

final class ContextWritePolicyTest extends TestCase
{
    public function test_provenance_is_server_derived_from_actor_relationship(): void
    {
        $this->assertSame(
            ContextWritePolicy::AUTHOR_SELECTED,
            ContextWritePolicy::provenanceFor(7, 7)
        );

        $this->assertSame(
            ContextWritePolicy::MODERATOR_ASSIGNED,
            ContextWritePolicy::provenanceFor(7, 99)
        );
    }

    public function test_relevance_limit_is_frozen_at_five(): void
    {
        $this->assertSame(5, ContextWritePolicy::MAX_ACTIVE_RELEVANCE);
    }
}
