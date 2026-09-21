<?php

namespace FlatRate\WikiContext\Projection;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;

/**
 * Materializes projected scope/ancestor rows for a staged graph version.
 * Never mutates the active-graph pointer; activation remains compare-and-switch only.
 */
final class ProjectionMaterializer
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{scope_row_count:int,ancestor_row_count:int,alias_row_count:int}
     */
    public function materializeChunk(string $graphVersionUuid, array $payload): array
    {
        $scopes = Arr::get($payload, 'scopes', Arr::get($payload, 'scope_rows', []));
        $ancestors = Arr::get($payload, 'ancestors', Arr::get($payload, 'ancestor_rows', []));
        $aliases = Arr::get($payload, 'aliases', Arr::get($payload, 'alias_rows', []));

        if (!is_array($scopes) || !is_array($ancestors) || !is_array($aliases)) {
            throw new \InvalidArgumentException('CHUNK_ROWS_INVALID');
        }

        if ($aliases !== []) {
            // Route-alias table is not yet migrated; reject non-empty alias payloads fail-closed.
            throw new \InvalidArgumentException('ALIAS_ROWS_UNSUPPORTED');
        }

        $scopeRows = [];
        foreach ($scopes as $index => $raw) {
            if (!is_array($raw)) {
                throw new \InvalidArgumentException('SCOPE_ROW_INVALID:' . $index);
            }
            $scopeRows[] = $this->normalizeScope($graphVersionUuid, $raw);
        }

        $ancestorRows = [];
        foreach ($ancestors as $index => $raw) {
            if (!is_array($raw)) {
                throw new \InvalidArgumentException('ANCESTOR_ROW_INVALID:' . $index);
            }
            $ancestorRows[] = $this->normalizeAncestor($graphVersionUuid, $raw);
        }

        if ($scopeRows !== []) {
            $this->db->table('flatrate_wiki_scopes')->insert($scopeRows);
        }
        if ($ancestorRows !== []) {
            $this->db->table('flatrate_wiki_scope_ancestors')->insert($ancestorRows);
        }

        return [
            'scope_row_count' => count($scopeRows),
            'ancestor_row_count' => count($ancestorRows),
            'alias_row_count' => 0,
        ];
    }

    /**
     * Validate staged rows for a graph version prior to activation.
     *
     * @return array{status:string,reason?:string,checks?:array<string,bool>}
     */
    public function validateVersion(object $version): array
    {
        $graphVersion = (string) $version->graph_version_uuid;
        $community = strtolower((string) $version->community_uuid);

        $scopeCount = (int) $this->db->table('flatrate_wiki_scopes')
            ->where('graph_version_uuid', $graphVersion)
            ->count();
        $ancestorCount = (int) $this->db->table('flatrate_wiki_scope_ancestors')
            ->where('graph_version_uuid', $graphVersion)
            ->count();

        if ($scopeCount !== (int) $version->scope_count) {
            return ['status' => 'reject', 'reason' => 'SCOPE_COUNT_MISMATCH'];
        }
        if ($ancestorCount !== (int) $version->ancestor_count) {
            return ['status' => 'reject', 'reason' => 'ANCESTOR_COUNT_MISMATCH'];
        }

        $scopes = $this->db->table('flatrate_wiki_scopes')
            ->where('graph_version_uuid', $graphVersion)
            ->get();

        $byId = [];
        $fingerprints = [];
        $routeKeys = [];
        $catchAllByParent = [];

        foreach ($scopes as $scope) {
            $id = strtolower((string) $scope->scope_uuid);
            if (isset($byId[$id])) {
                return ['status' => 'reject', 'reason' => 'DUPLICATE_SCOPE_UUID'];
            }
            $byId[$id] = $scope;

            if (strtolower((string) $scope->community_uuid) !== $community) {
                return ['status' => 'reject', 'reason' => 'SCOPE_COMMUNITY_MISMATCH'];
            }

            $fingerprint = (string) $scope->scope_fingerprint;
            if ($fingerprint === '' || isset($fingerprints[$fingerprint])) {
                return ['status' => 'reject', 'reason' => 'SCOPE_FINGERPRINT_CONFLICT'];
            }
            $fingerprints[$fingerprint] = true;

            $routeKey = $scope->route_key !== null ? (string) $scope->route_key : null;
            if ($routeKey !== null && $routeKey !== '') {
                if (isset($routeKeys[$routeKey])) {
                    return ['status' => 'reject', 'reason' => 'ROUTE_KEY_CONFLICT'];
                }
                $routeKeys[$routeKey] = true;
            }

            $parent = $scope->primary_parent_scope_uuid !== null
                ? strtolower((string) $scope->primary_parent_scope_uuid)
                : null;
            if ($parent !== null && $parent === $id) {
                return ['status' => 'reject', 'reason' => 'SELF_PARENT'];
            }

            if ((bool) $scope->is_catch_all) {
                $catchParent = $parent ?? '';
                if (isset($catchAllByParent[$catchParent])) {
                    return ['status' => 'reject', 'reason' => 'CATCH_ALL_DUPLICATE_PER_PARENT'];
                }
                $catchAllByParent[$catchParent] = $id;
            }
        }

        foreach ($scopes as $scope) {
            $parent = $scope->primary_parent_scope_uuid !== null
                ? strtolower((string) $scope->primary_parent_scope_uuid)
                : null;
            if ($parent !== null && !isset($byId[$parent])) {
                return ['status' => 'reject', 'reason' => 'UNKNOWN_PRIMARY_PARENT'];
            }
            if ((bool) $scope->is_catch_all && $parent !== null) {
                $parentRow = $byId[$parent];
                $parentBoard = $parentRow->owning_board_key !== null
                    ? (string) $parentRow->owning_board_key
                    : null;
                $scopeBoard = $scope->owning_board_key !== null
                    ? (string) $scope->owning_board_key
                    : null;
                if ($parentBoard !== null && $scopeBoard !== $parentBoard) {
                    return ['status' => 'reject', 'reason' => 'CATCH_ALL_BOARD_MISMATCH'];
                }
            }
        }

        $ancestors = $this->db->table('flatrate_wiki_scope_ancestors')
            ->where('graph_version_uuid', $graphVersion)
            ->get();

        foreach ($ancestors as $row) {
            $descendant = strtolower((string) $row->descendant_scope_uuid);
            $ancestor = strtolower((string) $row->ancestor_scope_uuid);
            $depth = (int) $row->depth;

            if (!isset($byId[$descendant]) || !isset($byId[$ancestor])) {
                return ['status' => 'reject', 'reason' => 'ANCESTOR_SCOPE_UNKNOWN'];
            }
            if ($descendant === $ancestor) {
                return ['status' => 'reject', 'reason' => 'ANCESTOR_SELF_ROW'];
            }
            if ($depth < 1) {
                return ['status' => 'reject', 'reason' => 'ANCESTOR_DEPTH_INVALID'];
            }
        }

        return [
            'status' => 'ok',
            'checks' => [
                'scope_count' => true,
                'ancestor_count' => true,
                'fingerprints_unique' => true,
                'route_keys_unique' => true,
                'parents_resolve' => true,
                'catch_all_invariant' => true,
                'ancestor_refs_valid' => true,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function normalizeScope(string $graphVersionUuid, array $raw): array
    {
        $scopeUuid = strtolower((string) Arr::get($raw, 'scope_uuid', Arr::get($raw, 'id', '')));
        $community = strtolower((string) Arr::get($raw, 'community_uuid', ''));
        $parent = Arr::get($raw, 'primary_parent_scope_uuid', Arr::get($raw, 'parent_id'));
        $parent = $parent === null || $parent === '' ? null : strtolower((string) $parent);
        $routeKey = Arr::get($raw, 'route_key', Arr::get($raw, 'route'));
        $routeKey = $routeKey === null || $routeKey === '' ? null : (string) $routeKey;
        $fingerprint = (string) Arr::get($raw, 'scope_fingerprint', Arr::get($raw, 'fingerprint', ''));
        $label = (string) Arr::get($raw, 'display_label', Arr::get($raw, 'label', ''));
        $type = (string) Arr::get($raw, 'scope_type', Arr::get($raw, 'type', ''));

        if (!$this->isUuid($scopeUuid) || !$this->isUuid($community) || $label === '' || $type === '' || $fingerprint === '') {
            throw new \InvalidArgumentException('SCOPE_ROW_REQUIRED_FIELDS');
        }
        if ($parent !== null && !$this->isUuid($parent)) {
            throw new \InvalidArgumentException('SCOPE_PARENT_INVALID');
        }

        return [
            'graph_version_uuid' => $graphVersionUuid,
            'scope_uuid' => $scopeUuid,
            'community_uuid' => $community,
            'scope_type' => $type,
            'owning_board_key' => $this->nullableString(Arr::get($raw, 'owning_board_key')),
            'route_key' => $routeKey,
            'display_label' => $label,
            'lifecycle_status' => (string) Arr::get($raw, 'lifecycle_status', 'active'),
            'primary_parent_scope_uuid' => $parent,
            'scope_fingerprint' => $fingerprint,
            'is_catch_all' => (bool) Arr::get($raw, 'is_catch_all', false),
            'discussion_capable' => (bool) Arr::get($raw, 'discussion_capable', true),
        ];
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function normalizeAncestor(string $graphVersionUuid, array $raw): array
    {
        $descendant = strtolower((string) Arr::get($raw, 'descendant_scope_uuid', Arr::get($raw, 'descendant', '')));
        $ancestor = strtolower((string) Arr::get($raw, 'ancestor_scope_uuid', Arr::get($raw, 'ancestor', '')));
        $depth = (int) Arr::get($raw, 'depth', 0);

        if (!$this->isUuid($descendant) || !$this->isUuid($ancestor) || $depth < 1) {
            throw new \InvalidArgumentException('ANCESTOR_ROW_REQUIRED_FIELDS');
        }

        return [
            'graph_version_uuid' => $graphVersionUuid,
            'descendant_scope_uuid' => $descendant,
            'ancestor_scope_uuid' => $ancestor,
            'depth' => $depth,
        ];
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $value
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
