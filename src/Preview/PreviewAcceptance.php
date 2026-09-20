<?php

namespace FlatRate\WikiContext\Preview;

/**
 * Freshness rules for future public rollout.
 * PUBLIC_ROLLOUT requires matching PASS receipt; no public UI bypass.
 */
final class PreviewAcceptance
{
    public static function isFresh(array $receipt, array $current): bool
    {
        if (($receipt['status'] ?? null) !== 'PASS') {
            return false;
        }

        foreach ([
            'active_graph_version_uuid',
            'extension_build_id',
            'wiki_contract_version',
            'directory_display_policy_digest',
        ] as $key) {
            if (($receipt[$key] ?? null) !== ($current[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
