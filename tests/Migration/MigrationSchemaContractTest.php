<?php

namespace FlatRate\WikiContext\Tests\Migration;

use PHPUnit\Framework\TestCase;

/**
 * Static migration contract checks (no DB). Runtime up/down proven in MariaDB harness.
 */
final class MigrationSchemaContractTest extends TestCase
{
    public function test_migration_files_exist(): void
    {
        $root = dirname(__DIR__, 2) . '/migrations';
        $expected = [
            '2026_09_20_000001_create_wiki_graph_versions.php',
            '2026_09_20_000002_create_wiki_projection_state.php',
            '2026_09_20_000003_create_wiki_scopes.php',
            '2026_09_20_000004_create_wiki_scope_ancestors.php',
            '2026_09_20_000005_create_wiki_projection_requests.php',
            '2026_09_20_000006_create_discussion_wiki_context.php',
            '2026_09_20_000007_create_discussion_wiki_relevance.php',
            '2026_09_20_000008_create_discussion_wiki_context_audit.php',
            '2026_09_20_000009_create_wiki_preview_acceptance.php',
        ];
        foreach ($expected as $file) {
            $this->assertFileExists($root . '/' . $file, $file);
            $src = file_get_contents($root . '/' . $file);
            $this->assertStringNotContainsString('REFERENCES discussions(id)', $src);
        }
        // Discussion FKs only on context + relevance migrations.
        foreach ([
            '2026_09_20_000006_create_discussion_wiki_context.php',
            '2026_09_20_000007_create_discussion_wiki_relevance.php',
        ] as $fkFile) {
            $src = file_get_contents($root . '/' . $fkFile);
            $this->assertStringContainsString("->on('discussions')", $src);
            $this->assertStringContainsString('->onDelete(\'cascade\')', $src);
        }
        $preview = file_get_contents($root . '/2026_09_20_000009_create_wiki_preview_acceptance.php');
        $this->assertStringContainsString('flatrate_wiki_preview_acceptance', $preview);
        $this->assertStringContainsString('directory_display_policy_digest', $preview);
        echo "PREVIEW_ACCEPTANCE_SCHEMA=PASS\n";
        echo "MIGRATION_FILES=PASS\n";
    }

    public function test_context_fk_is_prefix_aware_and_not_versioned_scope(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_09_20_000006_create_discussion_wiki_context.php');
        $this->assertStringContainsString("->on('discussions')", $src);
        $this->assertStringNotContainsString('flatrate_wiki_scopes', $src);
    }
}
