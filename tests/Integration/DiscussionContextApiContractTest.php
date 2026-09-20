<?php

namespace FlatRate\WikiContext\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class DiscussionContextApiContractTest extends TestCase
{
    public function test_create_fields_are_structured_create_only_and_not_model_properties(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Api/DiscussionWikiContextFields.php');

        $this->assertStringContainsString("Schema\\Arr::make('flatRateWikiContext')", $src);
        $this->assertStringContainsString("Schema\\Arr::make('flatRateWikiRelevance')", $src);
        $this->assertSame(2, substr_count($src, '->writableOnCreate()'));
        $this->assertSame(2, substr_count($src, '->visible(false)'));
        $this->assertSame(2, substr_count($src, '=> null'));
    }

    public function test_create_hook_uses_after_save_and_never_accepts_normal_update_path(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Listener/SaveDiscussionWikiContext.php');

        $this->assertStringContainsString('$event->discussion->afterSave', $src);
        $this->assertStringContainsString('wiki_context_update_requires_context_endpoint', $src);
        $this->assertStringContainsString('ContextWriteService', $src);
        $this->assertStringContainsString('CONTEXT_WRITES_ENABLED', $src);
    }

    public function test_correction_endpoint_is_context_only_and_revision_service_backed(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Api/Controllers/DiscussionContextController.php');

        $this->assertStringContainsString("assertCan('view'", $src);
        $this->assertStringContainsString("assertCan('tag'", $src);
        $this->assertStringContainsString('full_context_state_required', $src);
        $this->assertStringContainsString('$this->writes->correct', $src);
        $this->assertStringNotContainsString('title', $src);
        $this->assertStringNotContainsString('content', $src);
    }

    public function test_service_enforces_revision_board_graph_and_relevance_guards(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Context/ContextWriteService.php');

        foreach ([
            'context_revision_conflict',
            'expected_graph_version_stale',
            'cross_board_primary_move_not_allowed',
            'board_scope_mismatch',
            'relevance_limit_exceeded',
            'duplicate_relevance_scope',
            'primary_scope_cannot_be_relevance',
        ] as $guard) {
            $this->assertStringContainsString($guard, $src, $guard);
        }

        $this->assertStringContainsString('lockForUpdate()', file_get_contents(
            dirname(__DIR__, 2) . '/src/Repository/DiscussionContextRepository.php'
        ));
    }
}
