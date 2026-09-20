<?php

namespace FlatRate\WikiContext\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class ExtendRegistrationTest extends TestCase
{
    public function test_extend_registers_fail_closed_defaults_and_routes(): void
    {
        $root = dirname(__DIR__, 2);
        $extend = file_get_contents($root . '/extend.php');
        $gates = file_get_contents($root . '/src/Support/FeatureGates.php');

        foreach ([
            'flatrate-wiki.projection_sync_enabled',
            'flatrate-wiki.browse_routes_enabled',
            'flatrate-wiki.context_writes_enabled',
            'flatrate-wiki.derived_feeds_enabled',
            'flatrate-wiki.brand_root_derived_feeds_enabled',
            'flatrate-wiki.admin_ghost_preview_enabled',
            'flatrate-wiki.public_rollout_enabled',
        ] as $key) {
            $this->assertStringContainsString($key, $gates, $key);
        }

        $this->assertStringContainsString("->default(FeatureGates::PROJECTION_SYNC_ENABLED, '0')", $extend);
        $this->assertStringContainsString("->default(FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED, '0')", $extend);
        $this->assertStringContainsString("->default(FeatureGates::PUBLIC_ROLLOUT_ENABLED, '0')", $extend);
        $this->assertStringContainsString('flatrate.wiki.projection.stage', $extend);
        $this->assertStringContainsString('WikiScopeFilter::class', $extend);
        $this->assertStringNotContainsString('Extend\\ApiResource', $extend);
        $this->assertStringContainsString("->get('/flatrate-wiki/scopes/{id}'", $extend);
        $this->assertStringContainsString("->get('/flatrate-wiki/scopes/search'", $extend);
        $this->assertStringContainsString("->get('/flatrate-wiki/scopes/resolve'", $extend);
        $this->assertLessThan(
            strpos($extend, "->get('/flatrate-wiki/scopes/{id}'"),
            strpos($extend, "->get('/flatrate-wiki/scopes/search'")
        );
        $this->assertStringContainsString('ScopeSearchController::class', $extend);
        $this->assertStringContainsString('ScopeResolveController::class', $extend);
        $this->assertStringContainsString('flatRateWikiRelevanceMaxActive', $extend);
        $this->assertStringContainsString('ContextWritePolicy::MAX_ACTIVE_RELEVANCE', $extend);
        $this->assertStringContainsString('CaptureDiscussionWikiContext::class', $extend);
        $this->assertStringContainsString('PersistStartedDiscussionWikiContext::class', $extend);
        $this->assertStringContainsString('DiscussionSaving::class', $extend);
        $this->assertStringContainsString('DiscussionStarted::class', $extend);
        $this->assertStringContainsString('DiscussionContextController::class', $extend);
        $this->assertStringContainsString("->patch('/flatrate-wiki/discussions/{id}/context'", $extend);
        $this->assertStringContainsString("exemptRoute('flatrate.wiki.projection.stage')", $extend);
        $this->assertStringNotContainsString("exemptRoute('flatrate.wiki.discussions.context')", $extend);
        $this->assertStringNotContainsString('projection.rollback', $extend);
        $this->assertStringContainsString('NOT YET AUTHORIZED FOR PRODUCTION', $extend);
        echo "WIKI001D_CONTEXT_ROUTE_REGISTRATION=PASS\n";
        echo "WIKI001D_CREATE_CAPTURE_REGISTRATION=PASS\n";
        echo "WIKI001D_STARTED_PERSIST_REGISTRATION=PASS\n";
        echo "EXTEND_REGISTRATION=PASS\n";
    }

    public function test_composer_package_identity(): void
    {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
        $this->assertSame('flatrate/flarum-wiki-context', $composer['name']);
        $this->assertSame('flarum-extension', $composer['type']);
        $this->assertSame('MIT', $composer['license']);
        $this->assertArrayHasKey('FlatRate\\WikiContext\\', $composer['autoload']['psr-4']);
        $this->assertArrayNotHasKey('flatrate/flarum-forum-navigation', $composer['require'] ?? []);
        $this->assertArrayNotHasKey('flatrate/wiki-supabase-oauth', $composer['require'] ?? []);
        $this->assertStringContainsString('1.8.19', $composer['require']['flarum/core']);
    }
}
