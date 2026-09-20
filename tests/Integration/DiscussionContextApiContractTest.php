<?php

namespace FlatRate\WikiContext\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class DiscussionContextApiContractTest extends TestCase
{
    public function test_create_payload_is_captured_from_flarum_1_8_saving_event_without_api_resource_extender(): void
    {
        $capture = file_get_contents(dirname(__DIR__, 2) . '/src/Listener/CaptureDiscussionWikiContext.php');
        $extend = file_get_contents(dirname(__DIR__, 2) . '/extend.php');

        $this->assertStringContainsString("flatRateWikiContext", $capture);
        $this->assertStringContainsString("flatRateWikiRelevance", $capture);
        $this->assertStringContainsString("setRelation(", $capture);
        $this->assertStringContainsString("PENDING_RELATION", $capture);
        $this->assertStringNotContainsString("ApiResource", $extend);
        $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/src/Api/DiscussionWikiContextFields.php');
    }

    public function test_create_uses_saving_validation_then_started_persistence(): void
    {
        $capture = file_get_contents(dirname(__DIR__, 2) . '/src/Listener/CaptureDiscussionWikiContext.php');
        $persist = file_get_contents(dirname(__DIR__, 2) . '/src/Listener/PersistStartedDiscussionWikiContext.php');

        $this->assertStringContainsString('prepareInitial', $capture);
        $this->assertStringContainsString('wiki_context_update_requires_context_endpoint', $capture);
        $this->assertStringNotContainsString('afterSave(', $capture);

        $this->assertStringContainsString('persistInitialValidated', $persist);
        $this->assertStringContainsString('$discussion->delete()', $persist);
        $this->assertStringContainsString('CONTEXT_WRITES_ENABLED', $persist);
        $this->assertStringContainsString('PUBLIC_ROLLOUT_ENABLED', $persist);
    }

    public function test_correction_endpoint_is_context_only_and_revision_service_backed(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Api/Controllers/DiscussionContextController.php');

        $this->assertStringContainsString("assertCan('view'", $src);
        $this->assertStringContainsString("assertCan('tag'", $src);
        $this->assertStringContainsString('full_context_state_required', $src);
        $this->assertStringContainsString('$this->writes->correct', $src);
        $this->assertStringNotContainsString('$discussion->title', $src);
        $this->assertStringNotContainsString('->rename(', $src);
        $this->assertStringNotContainsString('PostReply', $src);
    }

    public function test_member_write_surfaces_require_public_rollout_in_addition_to_context_gate(): void
    {
        $listener = file_get_contents(dirname(__DIR__, 2) . '/src/Listener/CaptureDiscussionWikiContext.php');
        $controller = file_get_contents(dirname(__DIR__, 2) . '/src/Api/Controllers/DiscussionContextController.php');

        foreach ([$listener, $controller] as $src) {
            $this->assertStringContainsString('FeatureGates::CONTEXT_WRITES_ENABLED', $src);
            $this->assertStringContainsString('FeatureGates::PUBLIC_ROLLOUT_ENABLED', $src);
        }

        $this->assertStringContainsString('public_rollout_disabled', $controller);
    }

    public function test_generic_serializer_never_uses_admin_ghost_preview_as_public_read_gate(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/src/Api/Serializers/DiscussionWikiContextAttributes.php');

        $this->assertStringContainsString('FeatureGates::PUBLIC_ROLLOUT_ENABLED', $src);
        $this->assertStringNotContainsString('FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED', $src);
        $this->assertStringContainsString('Public rollout alone does not expose context', $src);
    }

    public function test_normal_tag_edits_are_checked_for_semantic_board_drift(): void
    {
        $listener = file_get_contents(dirname(__DIR__, 2) . '/src/Listener/CaptureDiscussionWikiContext.php');
        $service = file_get_contents(dirname(__DIR__, 2) . '/src/Context/ContextWriteService.php');

        $this->assertStringContainsString('assertExistingBoardCompatible', $listener);
        $this->assertStringContainsString('board_context_change_requires_coordinated_move', $service);
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
