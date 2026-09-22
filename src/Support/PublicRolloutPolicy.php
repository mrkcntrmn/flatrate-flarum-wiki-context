<?php

namespace FlatRate\WikiContext\Support;

use FlatRate\WikiContext\Preview\PreviewAcceptance;
use RuntimeException;

/**
 * Fail-closed policy for any future public-rollout enablement path.
 *
 * There is intentionally no mutation endpoint in P1B. Future production
 * enablement code must call assertFreshAcceptance() before flipping
 * flatrate-wiki.public_rollout_enabled.
 */
final class PublicRolloutPolicy
{
    /**
     * @param array<string,mixed>|null $receipt
     * @param array{
     *   active_graph_version_uuid:string,
     *   extension_build_id:string,
     *   wiki_contract_version:string,
     *   directory_display_policy_digest:string
     * } $current
     */
    public static function assertFreshAcceptance(?array $receipt, array $current): void
    {
        if ($receipt === null) {
            throw new RuntimeException('public_rollout_rejected:missing_receipt');
        }

        if (($receipt['status'] ?? null) !== 'PASS') {
            throw new RuntimeException('public_rollout_rejected:receipt_not_pass');
        }

        if (!PreviewAcceptance::isFresh($receipt, $current)) {
            $reason = self::staleReason($receipt, $current);
            throw new RuntimeException('public_rollout_rejected:' . $reason);
        }
    }

    public static function allows(?array $receipt, array $current): bool
    {
        try {
            self::assertFreshAcceptance($receipt, $current);

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $receipt
     * @param array<string,mixed> $current
     */
    private static function staleReason(array $receipt, array $current): string
    {
        foreach ([
            'active_graph_version_uuid' => 'stale_graph',
            'extension_build_id' => 'stale_build',
            'wiki_contract_version' => 'stale_contract',
            'directory_display_policy_digest' => 'stale_policy',
        ] as $key => $code) {
            if (($receipt[$key] ?? null) !== ($current[$key] ?? null)) {
                return $code;
            }
        }

        return 'stale_receipt';
    }
}
