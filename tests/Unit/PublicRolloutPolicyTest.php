<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Preview\PreviewAcceptance;
use FlatRate\WikiContext\Support\DirectoryDisplayPolicy;
use FlatRate\WikiContext\Support\PublicRolloutPolicy;
use FlatRate\WikiContext\Support\WikiBuild;
use FlatRate\WikiContext\Support\WikiContract;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicRolloutPolicyTest extends TestCase
{
    public function test_missing_fail_and_stale_bindings_are_rejected_fresh_pass_allowed(): void
    {
        $current = $this->current();

        $this->assertFalse(PublicRolloutPolicy::allows(null, $current));
        try {
            PublicRolloutPolicy::assertFreshAcceptance(null, $current);
            $this->fail('missing receipt must reject');
        } catch (RuntimeException $e) {
            $this->assertSame('public_rollout_rejected:missing_receipt', $e->getMessage());
        }

        $fail = array_merge($current, ['status' => 'FAIL']);
        $this->assertFalse(PublicRolloutPolicy::allows($fail, $current));
        try {
            PublicRolloutPolicy::assertFreshAcceptance($fail, $current);
            $this->fail('FAIL receipt must reject');
        } catch (RuntimeException $e) {
            $this->assertSame('public_rollout_rejected:receipt_not_pass', $e->getMessage());
        }

        $pass = array_merge($current, ['status' => 'PASS']);
        $this->assertTrue(PublicRolloutPolicy::allows($pass, $current));
        PublicRolloutPolicy::assertFreshAcceptance($pass, $current);

        foreach ([
            'active_graph_version_uuid' => 'stale_graph',
            'extension_build_id' => 'stale_build',
            'wiki_contract_version' => 'stale_contract',
            'directory_display_policy_digest' => 'stale_policy',
        ] as $key => $code) {
            $stale = $pass;
            $stale[$key] = $stale[$key] . '-changed';
            $this->assertFalse(PreviewAcceptance::isFresh($stale, $current));
            $this->assertFalse(PublicRolloutPolicy::allows($stale, $current));
            try {
                PublicRolloutPolicy::assertFreshAcceptance($stale, $current);
                $this->fail($code . ' must reject');
            } catch (RuntimeException $e) {
                $this->assertSame('public_rollout_rejected:' . $code, $e->getMessage());
            }
        }

        echo "PUBLIC_TOGGLE_WITHOUT_RECEIPT=REJECTED\n";
        echo "PUBLIC_TOGGLE_WITH_STALE_RECEIPT=REJECTED\n";
        echo "GRAPH_CHANGE_STALES_RECEIPT=PASS\n";
        echo "BUILD_CHANGE_STALES_RECEIPT=PASS\n";
        echo "CONTRACT_CHANGE_STALES_RECEIPT=PASS\n";
        echo "POLICY_CHANGE_STALES_RECEIPT=PASS\n";
    }

    private function current(): array
    {
        return [
            'active_graph_version_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'extension_build_id' => WikiBuild::BUILD_ID,
            'wiki_contract_version' => WikiContract::VERSION,
            'directory_display_policy_digest' => DirectoryDisplayPolicy::digest(),
        ];
    }
}
