<?php

namespace FlatRate\WikiContext\Projection;

use FlatRate\WikiContext\Support\FeatureGates;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;

/**
 * Projection protocol v2 orchestration skeleton.
 * Stage → chunk → validate → activate. No production enablement.
 */
final class ProjectionService
{
    public function __construct(
        private ConnectionInterface $db,
        private SettingsReader $settings
    ) {
    }

    public function projectionSyncEnabled(): bool
    {
        return $this->settings->bool(FeatureGates::PROJECTION_SYNC_ENABLED);
    }

    /**
     * Stage a graph version manifest without activating.
     *
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>
     */
    public function stage(array $manifest): array
    {
        $this->assertSyncGate();

        $graphVersion = strtolower((string) Arr::get($manifest, 'graph_version_id', ''));
        $community = strtolower((string) Arr::get($manifest, 'community_id', ''));
        $generation = (int) Arr::get($manifest, 'generation', 0);
        $parent = Arr::get($manifest, 'parent_graph_version_id');
        $parent = $parent === null || $parent === '' ? null : strtolower((string) $parent);
        $schemaVersion = (string) Arr::get($manifest, 'schema_version', '');
        $contentDigest = (string) Arr::get($manifest, 'content_digest', '');
        $chunkCount = (int) Arr::get($manifest, 'expected_chunk_count', Arr::get($manifest, 'chunk_count', 0));
        $nodeCount = (int) Arr::get($manifest, 'node_count', 0);
        $scopeCount = (int) Arr::get($manifest, 'scope_count', 0);
        $ancestorCount = (int) Arr::get($manifest, 'ancestor_count', 0);

        $existing = $this->db->table('flatrate_wiki_graph_versions')
            ->where('graph_version_uuid', $graphVersion)
            ->first();

        if ($existing) {
            $classification = ProjectionAuth::classifyVersion(
                $generation,
                (int) $existing->generation,
                true,
                hash_equals((string) $existing->content_digest, $contentDigest)
            );
            if ($classification === 'VERSION_DIGEST_CONFLICT') {
                return ['status' => 'reject', 'reason' => $classification];
            }
            if ($classification === 'ALREADY_ACCEPTED') {
                return ['status' => 'already_accepted', 'graph_version_uuid' => $graphVersion];
            }
        }

        $active = $this->db->table('flatrate_wiki_projection_state')
            ->where('community_uuid', $community)
            ->first();
        $activeGeneration = $active ? (int) $active->active_generation : 0;

        if ($generation < $activeGeneration) {
            return ['status' => 'reject', 'reason' => 'STALE_GENERATION'];
        }

        $now = date('Y-m-d H:i:s');
        $row = [
            'graph_version_uuid' => $graphVersion,
            'community_uuid' => $community,
            'generation' => $generation,
            'parent_graph_version_uuid' => $parent,
            'schema_version' => $schemaVersion,
            'content_digest' => $contentDigest,
            'node_count' => $nodeCount,
            'scope_count' => $scopeCount,
            'ancestor_count' => $ancestorCount,
            'chunk_count' => $chunkCount,
            'status' => 'staging',
            'received_at' => $now,
            'validated_at' => null,
            'activated_at' => null,
        ];

        if ($existing) {
            $this->db->table('flatrate_wiki_graph_versions')
                ->where('graph_version_uuid', $graphVersion)
                ->update($row);
        } else {
            $this->db->table('flatrate_wiki_graph_versions')->insert($row);
        }

        return [
            'status' => 'staged',
            'graph_version_uuid' => $graphVersion,
            'activated' => false,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function chunk(array $payload): array
    {
        $this->assertSyncGate();

        $graphVersion = strtolower((string) Arr::get($payload, 'graph_version_id', ''));
        $chunkIndex = (int) Arr::get($payload, 'chunk_index', -1);
        $chunkDigest = (string) Arr::get($payload, 'chunk_digest', '');
        $chunkCount = (int) Arr::get($payload, 'chunk_count', 0);

        $existing = $this->db->table('flatrate_wiki_projection_chunks')
            ->where('graph_version_uuid', $graphVersion)
            ->where('chunk_index', $chunkIndex)
            ->first();

        $classification = ProjectionAuth::classifyChunk(
            (bool) $existing,
            $existing ? hash_equals((string) $existing->chunk_digest, $chunkDigest) : false
        );

        if ($classification === 'CHUNK_DIGEST_CONFLICT') {
            return ['status' => 'reject', 'reason' => $classification];
        }
        if ($classification === 'ALREADY_ACCEPTED') {
            return ['status' => 'already_accepted', 'chunk_index' => $chunkIndex];
        }

        $this->db->table('flatrate_wiki_projection_chunks')->insert([
            'graph_version_uuid' => $graphVersion,
            'chunk_index' => $chunkIndex,
            'chunk_count' => $chunkCount,
            'chunk_digest' => $chunkDigest,
            'scope_row_count' => (int) Arr::get($payload, 'scope_row_count', 0),
            'ancestor_row_count' => (int) Arr::get($payload, 'ancestor_row_count', 0),
            'alias_row_count' => (int) Arr::get($payload, 'alias_row_count', 0),
            'received_at' => date('Y-m-d H:i:s'),
        ]);

        // Chunk rows are staged only; active projection is never partially mutated.
        return [
            'status' => 'accepted',
            'chunk_index' => $chunkIndex,
            'active_projection_mutated' => false,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function validate(array $payload): array
    {
        $this->assertSyncGate();

        $graphVersion = strtolower((string) Arr::get($payload, 'graph_version_id', ''));
        $version = $this->db->table('flatrate_wiki_graph_versions')
            ->where('graph_version_uuid', $graphVersion)
            ->first();

        if (!$version) {
            return ['status' => 'reject', 'reason' => 'UNKNOWN_VERSION'];
        }

        $chunks = $this->db->table('flatrate_wiki_projection_chunks')
            ->where('graph_version_uuid', $graphVersion)
            ->count();

        if ($chunks < (int) $version->chunk_count) {
            return ['status' => 'reject', 'reason' => 'MISSING_CHUNKS'];
        }

        $this->db->table('flatrate_wiki_graph_versions')
            ->where('graph_version_uuid', $graphVersion)
            ->update([
                'status' => 'validated',
                'validated_at' => date('Y-m-d H:i:s'),
            ]);

        return [
            'status' => 'validated',
            'graph_version_uuid' => $graphVersion,
            'activated' => false,
        ];
    }

    /**
     * Compare-and-switch activation. Concurrent stale activation → REJECT.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function activate(array $payload): array
    {
        $this->assertSyncGate();

        $graphVersion = strtolower((string) Arr::get($payload, 'graph_version_id', ''));

        return $this->db->transaction(function () use ($graphVersion) {
            $version = $this->db->table('flatrate_wiki_graph_versions')
                ->where('graph_version_uuid', $graphVersion)
                ->lockForUpdate()
                ->first();

            if (!$version || $version->status !== 'validated') {
                return ['status' => 'reject', 'reason' => 'NOT_VALIDATED'];
            }

            $community = (string) $version->community_uuid;
            $state = $this->db->table('flatrate_wiki_projection_state')
                ->where('community_uuid', $community)
                ->lockForUpdate()
                ->first();

            $activeUuid = $state ? (string) $state->active_graph_version_uuid : null;
            $parent = $version->parent_graph_version_uuid !== null
                ? (string) $version->parent_graph_version_uuid
                : null;

            // Bootstrap: first activation may have null parent and null active.
            if ($activeUuid !== null && $parent !== $activeUuid) {
                return [
                    'status' => 'reject',
                    'reason' => 'PARENT_NOT_ACTIVE',
                    'active_graph_unchanged' => true,
                ];
            }

            $now = date('Y-m-d H:i:s');

            if ($state) {
                $updated = $this->db->table('flatrate_wiki_projection_state')
                    ->where('community_uuid', $community)
                    ->where('active_graph_version_uuid', $activeUuid)
                    ->update([
                        'active_graph_version_uuid' => $graphVersion,
                        'active_generation' => (int) $version->generation,
                        'activated_at' => $now,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    return [
                        'status' => 'reject',
                        'reason' => 'ACTIVATION_RACE',
                        'active_graph_unchanged' => true,
                    ];
                }

                if ($activeUuid) {
                    $this->db->table('flatrate_wiki_graph_versions')
                        ->where('graph_version_uuid', $activeUuid)
                        ->update(['status' => 'superseded']);
                }
            } else {
                $this->db->table('flatrate_wiki_projection_state')->insert([
                    'community_uuid' => $community,
                    'active_graph_version_uuid' => $graphVersion,
                    'active_generation' => (int) $version->generation,
                    'activated_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->db->table('flatrate_wiki_graph_versions')
                ->where('graph_version_uuid', $graphVersion)
                ->update([
                    'status' => 'active',
                    'activated_at' => $now,
                ]);

            return [
                'status' => 'activated',
                'graph_version_uuid' => $graphVersion,
                'prior_active' => $activeUuid,
            ];
        });
    }

    private function assertSyncGate(): void
    {
        if (!$this->projectionSyncEnabled()) {
            throw new \RuntimeException('projection_sync_disabled');
        }
    }
}
