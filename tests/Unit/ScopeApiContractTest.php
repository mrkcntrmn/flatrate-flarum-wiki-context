<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Api\Controllers\ScopeResolveController;
use FlatRate\WikiContext\Api\Controllers\ScopeSearchController;
use FlatRate\WikiContext\Api\Controllers\ScopeShowController;
use FlatRate\WikiContext\Context\ContextWritePolicy;
use PHPUnit\Framework\TestCase;

final class ScopeApiContractTest extends TestCase
{
    public function test_scope_show_emits_active_graph_version_and_relevance_max(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Api/Controllers/ScopeShowController.php');

        $this->assertStringContainsString("'activeGraphVersionId'", $src);
        $this->assertStringContainsString('activeGraphVersionId(', $src);
        $this->assertStringContainsString("'relevanceMaxActive'", $src);
        $this->assertStringContainsString('ContextWritePolicy::MAX_ACTIVE_RELEVANCE', $src);
        $this->assertStringNotContainsString('projection_hmac', $src);
        $this->assertStringNotContainsString('actor_id', $src);

        echo "WIKI001F_SCOPE_SHOW_GRAPH_META_CONTRACT=PASS\n";
    }

    public function test_scope_search_bounds_and_blank_query_policy(): void
    {
        $this->assertSame(20, ScopeSearchController::MAX_RESULTS);
        $this->assertSame(64, ScopeSearchController::MAX_QUERY_LENGTH);

        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Api/Controllers/ScopeSearchController.php');
        $this->assertStringContainsString('searchActiveDiscussionCapable', $src);
        $this->assertStringContainsString("'blankQueryDumpsGraph' => false", $src);
        $this->assertStringContainsString("'discussionCapableOnly' => true", $src);

        echo "WIKI001F_SCOPE_SEARCH_CONTRACT=PASS\n";
    }

    public function test_scope_resolve_is_bounded(): void
    {
        $this->assertSame(50, ScopeResolveController::MAX_IDS);
        $this->assertSame(5, ContextWritePolicy::MAX_ACTIVE_RELEVANCE);

        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Api/Controllers/ScopeResolveController.php');
        $this->assertStringContainsString('resolveActive', $src);
        $this->assertStringContainsString("'maxIds' => self::MAX_IDS", $src);

        echo "WIKI001F_SCOPE_RESOLVE_CONTRACT=PASS\n";
    }

    public function test_scope_show_controller_exists_for_graph_meta(): void
    {
        $this->assertTrue(class_exists(ScopeShowController::class));
    }
}
