<?php

namespace FlatRate\WikiContext\Projection;

use FlatRate\WikiContext\Support\FeatureGates;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;

/**
 * Projection protocol v2 orchestration.
 * Stage → chunk (materialize rows) → validate → activate.
 * Active pointer flips only on activate; prior active rows remain intact on failure.
 */
final class ProjectionService
{
    public function __construct(
        private ConnectionInterface $db,
        private SettingsReader $settings,
        private ?ProjectionMaterializer $materializer = null
    ) {
        $this->materializer ??= new ProjectionMaterializer($this->db);
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

        $version = $this->db->table('flatrate_wiki_graph_versions')
            ->where('graph_version_uuid', $graphVersion)
            ->first();
        if (!$version || !in_array((string) $version->status, ['staging', 'validated'], true)) {
            return ['status' => 'reject', 'reason' => 'VERSION_NOT_STAGING'];
        }

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
            return [
                'status' => 'already_accepted',
                'chunk_index' => $chunkIndex,
                'active_projection_mutated' => false,
            ];
        }

        try {
            return $this->db->transaction(function () use (
                $payload,
                $graphVersion,
                $chunkIndex,
                $chunkDigest,
                $chunkCount
            ) {
                // Re-entering staging after validate requires clear status.
                $this->db->table('flatrate_wiki_graph_versions')
                    ->where('graph_version_uuid', $graphVersion)
                    ->update([
                        'status' => 'staging',
                        'validated_at' => null,
                    ]);

                $counts = $this->materializer->materializeChunk($graphVersion, $payload);

                $declaredScope = (int) Arr::get($payload, 'scope_row_count', $counts['scope_row_count']);
                $declaredAncestor = (int) Arr::get($payload, 'ancestor_row_count', $counts['ancestor_row_count']);
                $declaredAlias = (int) Arr::get($payload, 'alias_row_count', $counts['alias_row_count']);

                if ($declaredScope !== $counts['scope_row_count']
                    || $declaredAncestor !== $counts['ancestor_row_count']
                    || $declaredAlias !== $counts['alias_row_count']) {
                    throw new \RuntimeException('CHUNK_COUNT_MISMATCH');
                }

                $this->db->table('flatrate_wiki_projection_chunks')->insert([
                    'graph_version_uuid' => $graphVersion,
                    'chunk_index' => $chunkIndex,
                    'chunk_count' => $chunkCount,
                    'chunk_digest' => $chunkDigest,
                    'scope_row_count' => $counts['scope_row_count'],
                    'ancestor_row_count' => $counts['ancestor_row_count'],
                    'alias_row_count' => $counts['alias_row_count'],
                    'received_at' => date('Y-m-d H:i:s'),
                ]);

                return [
                    'status' => 'accepted',
                    'chunk_index' => $chunkIndex,
                    'scope_row_count' => $counts['scope_row_count'],
                    'ancestor_row_count' => $counts['ancestor_row_count'],
                    'alias_row_count' => $counts['alias_row_count'],
                    'active_projection_mutated' => false,
                ];
            });
        } catch (\InvalidArgumentException $e) {
            return ['status' => 'reject', 'reason' => $e->getMessage()];
        } catch (\RuntimeException $e) {
            return ['status' => 'reject', 'reason' => $e->getMessage()];
        }
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

        $integrity = $this->materializer->validateVersion($version);
        if (($integrity['status'] ?? '') !== 'ok') {
            return [
                'status' => 'reject',
                'reason' => $integrity['reason'] ?? 'INTEGRITY_FAILED',
                'activated' => false,
            ];
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
            'checks' => $integrity['checks'] ?? [],
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

            // Re-check integrity inside activation transaction.
            $integrity = $this->materializer->validateVersion($version);
            if (($integrity['status'] ?? '') !== 'ok') {
                return [
                    'status' => 'reject',
                    'reason' => $integrity['reason'] ?? 'INTEGRITY_FAILED',
                    'active_graph_unchanged' => true,
                ];
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

            $scopeCount = (int) $this->db->table('flatrate_wiki_scopes')
                ->where('graph_version_uuid', $graphVersion)
                ->count();
            $ancestorCount = (int) $this->db->table('flatrate_wiki_scope_ancestors')
                ->where('graph_version_uuid', $graphVersion)
                ->count();

            return [
                'status' => 'activated',
                'graph_version_uuid' => $graphVersion,
                'prior_active' => $activeUuid,
                'scope_count' => $scopeCount,
                'ancestor_count' => $ancestorCount,
                'reconcile' => [
                    'scope_count_matches_manifest' => $scopeCount === (int) $version->scope_count,
                    'ancestor_count_matches_manifest' => $ancestorCount === (int) $version->ancestor_count,
                ],
            ];
        });
    }

    /**
     * Read-only reconciliation report for the active (or specified) graph.
     *
     * @return array<string,mixed>
     */
    public function reconcile(?string $communityUuid = null): array
    {
        $query = $this->db->table('flatrate_wiki_projection_state');
        if ($communityUuid !== null && $communityUuid !== '') {
            $query->where('community_uuid', strtolower($communityUuid));
        }
        $states = $query->get();

        $reports = [];
        foreach ($states as $state) {
            $graph = (string) $state->active_graph_version_uuid;
            $version = $this->db->table('flatrate_wiki_graph_versions')
                ->where('graph_version_uuid', $graph)
                ->first();
            $scopeCount = (int) $this->db->table('flatrate_wiki_scopes')
                ->where('graph_version_uuid', $graph)
                ->count();
            $ancestorCount = (int) $this->db->table('flatrate_wiki_scope_ancestors')
                ->where('graph_version_uuid', $graph)
                ->count();

            $integrity = $version ? $this->materializer->validateVersion($version) : [
                'status' => 'reject',
                'reason' => 'MISSING_VERSION_ROW',
            ];

            $reports[] = [
                'community_uuid' => (string) $state->community_uuid,
                'active_graph_version_uuid' => $graph,
                'active_generation' => (int) $state->active_generation,
                'manifest_scope_count' => $version ? (int) $version->scope_count : null,
                'manifest_ancestor_count' => $version ? (int) $version->ancestor_count : null,
                'actual_scope_count' => $scopeCount,
                'actual_ancestor_count' => $ancestorCount,
                'integrity_status' => $integrity['status'] ?? 'reject',
                'integrity_reason' => $integrity['reason'] ?? null,
                'ok' => ($integrity['status'] ?? '') === 'ok'
                    && $version
                    && $scopeCount === (int) $version->scope_count
                    && $ancestorCount === (int) $version->ancestor_count,
            ];
        }

        return [
            'status' => 'reconciled',
            'dry_run' => true,
            'production_mutation' => false,
            'communities' => $reports,
        ];
    }

    /**
     * CLI-only rollback: switch active pointer to a previously accepted historical version.
     * Does not delete superseded rows. Remote API remains forbidden.
     *
     * @return array<string,mixed>
     */
    public function rollback(string $targetVersionUuid, string $reason, bool $dryRun = true): array
    {
        $targetVersionUuid = strtolower($targetVersionUuid);

        $target = $this->db->table('flatrate_wiki_graph_versions')
            ->where('graph_version_uuid', $targetVersionUuid)
            ->first();

        if (!$target) {
            return ['status' => 'reject', 'reason' => 'UNKNOWN_VERSION', 'dry_run' => $dryRun];
        }

        $integrity = $this->materializer->validateVersion($target);
        if (($integrity['status'] ?? '') !== 'ok') {
            return [
                'status' => 'reject',
                'reason' => $integrity['reason'] ?? 'TARGET_INTEGRITY_FAILED',
                'dry_run' => $dryRun,
            ];
        }

        $community = (string) $target->community_uuid;
        $state = $this->db->table('flatrate_wiki_projection_state')
            ->where('community_uuid', $community)
            ->first();

        if (!$state) {
            return ['status' => 'reject', 'reason' => 'NO_ACTIVE_STATE', 'dry_run' => $dryRun];
        }

        $current = (string) $state->active_graph_version_uuid;
        if ($current === $targetVersionUuid) {
            return [
                'status' => 'already_active',
                'graph_version_uuid' => $targetVersionUuid,
                'dry_run' => $dryRun,
                'reason' => $reason,
            ];
        }

        if ($dryRun) {
            return [
                'status' => 'dry_run_ok',
                'from' => $current,
                'to' => $targetVersionUuid,
                'reason' => $reason,
                'dry_run' => true,
                'production_mutation' => false,
            ];
        }

        return $this->db->transaction(function () use ($target, $targetVersionUuid, $community, $current, $reason) {
            $state = $this->db->table('flatrate_wiki_projection_state')
                ->where('community_uuid', $community)
                ->lockForUpdate()
                ->first();

            if (!$state || (string) $state->active_graph_version_uuid !== $current) {
                return [
                    'status' => 'reject',
                    'reason' => 'ACTIVE_CHANGED',
                    'active_graph_unchanged' => true,
                ];
            }

            $now = date('Y-m-d H:i:s');
            $updated = $this->db->table('flatrate_wiki_projection_state')
                ->where('community_uuid', $community)
                ->where('active_graph_version_uuid', $current)
                ->update([
                    'active_graph_version_uuid' => $targetVersionUuid,
                    'active_generation' => (int) $target->generation,
                    'activated_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                return [
                    'status' => 'reject',
                    'reason' => 'ROLLBACK_RACE',
                    'active_graph_unchanged' => true,
                ];
            }

            $this->db->table('flatrate_wiki_graph_versions')
                ->where('graph_version_uuid', $current)
                ->update(['status' => 'superseded']);

            $this->db->table('flatrate_wiki_graph_versions')
                ->where('graph_version_uuid', $targetVersionUuid)
                ->update([
                    'status' => 'active',
                    'activated_at' => $now,
                ]);

            return [
                'status' => 'rolled_back',
                'from' => $current,
                'to' => $targetVersionUuid,
                'reason' => $reason,
                'dry_run' => false,
                'remote_rollback_api' => false,
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
