<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Preview\PreviewAcceptance;
use FlatRate\WikiContext\Support\GhostPreviewPolicy;
use PHPUnit\Framework\TestCase;

final class GhostPreviewPolicyTest extends TestCase
{
    public function test_ghost_preview_contract(): void
    {
        $c = GhostPreviewPolicy::contract();
        $this->assertFalse($c['admin_ghost_preview_enabled_default']);
        $this->assertFalse($c['public_rollout_enabled_default']);
        $this->assertTrue($c['same_user_facing_components']);
        $this->assertFalse($c['separate_preview_ui_implementation']);
        $this->assertFalse($c['admin_elevated_visibility_for_user_preview']);
        $this->assertTrue($c['read_only']);
        $this->assertSame('standard_member', $c['default_audience']);
        $this->assertContains('guest', $c['audience_profiles']);
        $this->assertContains('standard_member', $c['audience_profiles']);
        echo "GHOST_PREVIEW_POLICY_CONTRACT=PASS\n";
    }

    public function test_acceptance_freshness(): void
    {
        $current = [
            'active_graph_version_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'extension_build_id' => \FlatRate\WikiContext\Support\WikiBuild::BUILD_ID,
            'wiki_contract_version' => \FlatRate\WikiContext\Support\WikiContract::VERSION,
            'directory_display_policy_digest' => \FlatRate\WikiContext\Support\DirectoryDisplayPolicy::digest(),
        ];
        $receipt = array_merge($current, ['status' => 'PASS']);
        $this->assertTrue(PreviewAcceptance::isFresh($receipt, $current));
        $stale = $receipt;
        $stale['extension_build_id'] = 'build-changed';
        $this->assertFalse(PreviewAcceptance::isFresh($stale, $current));
        $staleContract = $receipt;
        $staleContract['wiki_contract_version'] = 'contract-changed';
        $this->assertFalse(PreviewAcceptance::isFresh($staleContract, $current));
    }
}
