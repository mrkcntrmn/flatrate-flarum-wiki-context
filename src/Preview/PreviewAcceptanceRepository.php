<?php

namespace FlatRate\WikiContext\Preview;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Persistence for wiki_preview_acceptance receipts.
 */
final class PreviewAcceptanceRepository
{
    public const TABLE = 'flatrate_wiki_preview_acceptance';

    public function __construct(private ConnectionInterface $db)
    {
    }

    public function findLatestPass(): ?array
    {
        $row = $this->db->table(self::TABLE)
            ->where('status', 'PASS')
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findLatest(): ?array
    {
        $row = $this->db->table(self::TABLE)
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    /**
     * @param array<string,mixed> $receipt
     */
    public function insertPass(array $receipt): array
    {
        if (($receipt['status'] ?? null) !== 'PASS') {
            throw new RuntimeException('preview_receipt_insert_requires_pass');
        }

        foreach ([
            'accepted_by_user_id',
            'accepted_at',
            'active_graph_version_uuid',
            'extension_build_id',
            'wiki_contract_version',
            'directory_display_policy_digest',
            'audience_profiles_verified',
            'fixture_results_digest',
            'status',
        ] as $key) {
            if (!array_key_exists($key, $receipt) || $receipt[$key] === null || $receipt[$key] === '') {
                throw new RuntimeException('preview_receipt_missing_field:' . $key);
            }
        }

        $id = $this->db->table(self::TABLE)->insertGetId([
            'accepted_by_user_id' => (int) $receipt['accepted_by_user_id'],
            'accepted_at' => $receipt['accepted_at'],
            'active_graph_version_uuid' => $receipt['active_graph_version_uuid'],
            'extension_build_id' => $receipt['extension_build_id'],
            'wiki_contract_version' => $receipt['wiki_contract_version'],
            'directory_display_policy_digest' => $receipt['directory_display_policy_digest'],
            'audience_profiles_verified' => $receipt['audience_profiles_verified'],
            'fixture_results_digest' => $receipt['fixture_results_digest'],
            'status' => 'PASS',
        ]);

        return array_merge($receipt, ['id' => $id]);
    }

    private function toArray(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'accepted_by_user_id' => (int) $row->accepted_by_user_id,
            'accepted_at' => (string) $row->accepted_at,
            'active_graph_version_uuid' => (string) $row->active_graph_version_uuid,
            'extension_build_id' => (string) $row->extension_build_id,
            'wiki_contract_version' => (string) $row->wiki_contract_version,
            'directory_display_policy_digest' => (string) $row->directory_display_policy_digest,
            'audience_profiles_verified' => (string) $row->audience_profiles_verified,
            'fixture_results_digest' => (string) $row->fixture_results_digest,
            'status' => (string) $row->status,
        ];
    }
}
