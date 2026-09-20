<?php

namespace FlatRate\WikiContext\Repository;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final class ScopeReadRepository
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function findActive(string $scopeUuid): ?array
    {
        $row = $this->activeScopes()
            ->where('s.scope_uuid', strtolower($scopeUuid))
            ->first($this->publicColumns());

        return $row ? $this->publicScope($row) : null;
    }

    /**
     * Public-safe active projection graph UUID for the community owning this scope.
     * Never derived from client/query input — only from projection_state joined to the active scope.
     */
    public function activeGraphVersionId(string $scopeUuid): ?string
    {
        $row = $this->activeScopes()
            ->where('s.scope_uuid', strtolower($scopeUuid))
            ->first(['ps.active_graph_version_uuid']);

        if ($row === null || $row->active_graph_version_uuid === null) {
            return null;
        }

        return strtolower((string) $row->active_graph_version_uuid);
    }

    /**
     * Bounded active discussion-capable scope search for relevance picking.
     * Blank queries return no rows (no full-graph dump).
     *
     * @return list<array<string,mixed>>
     */
    public function searchActiveDiscussionCapable(string $query, int $limit = 20): array
    {
        $query = trim($query);
        $query = function_exists('mb_substr') ? mb_substr($query, 0, 64) : substr($query, 0, 64);
        $limit = max(1, min(20, $limit));

        if ($query === '') {
            return [];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        $rows = $this->activeScopes()
            ->where('s.discussion_capable', true)
            ->where('s.display_label', 'like', '%' . $escaped . '%')
            ->orderBy('s.display_label')
            ->orderBy('s.scope_uuid')
            ->limit($limit)
            ->get($this->publicColumns());

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->publicScope($row);
        }

        return $items;
    }

    /**
     * Bounded batch resolver for public-safe active scope metadata.
     *
     * @param list<string> $scopeUuids
     * @return list<array<string,mixed>>
     */
    public function resolveActive(array $scopeUuids, int $maxIds = 50): array
    {
        $maxIds = max(1, min(50, $maxIds));
        $normalized = [];

        foreach ($scopeUuids as $raw) {
            if (!is_string($raw)) {
                continue;
            }
            $id = strtolower(trim($raw));
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
                continue;
            }
            if (!in_array($id, $normalized, true)) {
                $normalized[] = $id;
            }
            if (count($normalized) >= $maxIds) {
                break;
            }
        }

        if ($normalized === []) {
            return [];
        }

        $rows = $this->activeScopes()
            ->whereIn('s.scope_uuid', $normalized)
            ->orderBy('s.display_label')
            ->orderBy('s.scope_uuid')
            ->get($this->publicColumns());

        $byId = [];
        foreach ($rows as $row) {
            $scope = $this->publicScope($row);
            $byId[$scope['id']] = $scope;
        }

        $ordered = [];
        foreach ($normalized as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    public function breadcrumbs(string $scopeUuid): array
    {
        $scopeUuid = strtolower($scopeUuid);
        $target = $this->findActive($scopeUuid);

        if ($target === null) {
            return [];
        }

        $rows = $this->db->table('flatrate_wiki_scope_ancestors as a')
            ->join('flatrate_wiki_projection_state as ps', function ($join) {
                $join->on('ps.active_graph_version_uuid', '=', 'a.graph_version_uuid');
            })
            ->join('flatrate_wiki_scopes as s', function ($join) {
                $join->on('s.graph_version_uuid', '=', 'a.graph_version_uuid')
                    ->on('s.scope_uuid', '=', 'a.ancestor_scope_uuid')
                    ->on('s.community_uuid', '=', 'ps.community_uuid');
            })
            ->where('a.descendant_scope_uuid', $scopeUuid)
            ->where('a.ancestor_scope_uuid', '<>', $scopeUuid)
            ->where('s.lifecycle_status', 'active')
            ->orderByDesc('a.depth')
            ->orderBy('s.display_label')
            ->orderBy('s.scope_uuid')
            ->get($this->publicColumns());

        $breadcrumbs = [];
        foreach ($rows as $row) {
            $breadcrumbs[] = $this->publicScope($row);
        }

        $breadcrumbs[] = $target;

        return $breadcrumbs;
    }

    public function childCount(string $scopeUuid): int
    {
        return (int) $this->childrenQuery($scopeUuid)->count();
    }

    public function normalChildCount(string $scopeUuid): int
    {
        return (int) $this->normalChildrenQuery($scopeUuid)->count();
    }

    public function catchAllChild(string $scopeUuid): ?array
    {
        $row = $this->childrenQuery($scopeUuid)
            ->where('s.is_catch_all', true)
            ->orderBy('s.display_label')
            ->orderBy('s.scope_uuid')
            ->first($this->publicColumns());

        return $row ? $this->publicScope($row) : null;
    }

    /**
     * Paginate/search normal direct children only.
     * Catch-all is returned separately so it never consumes the normal budget.
     *
     * @return array{items:list<array<string,mixed>>,total:int,offset:int,limit:int,has_more:bool,query:string}
     */
    public function normalChildren(
        string $scopeUuid,
        int $offset,
        int $limit,
        ?string $query = null
    ): array {
        $offset = max(0, $offset);
        $limit = max(1, min(50, $limit));
        $query = trim((string) $query);
        $query = function_exists('mb_substr') ? mb_substr($query, 0, 64) : substr($query, 0, 64);

        $base = $this->normalChildrenQuery($scopeUuid);

        if ($query !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
            $base->where('s.display_label', 'like', '%' . $escaped . '%');
        }

        $total = (int) (clone $base)->count();

        $rows = $base
            ->orderBy('s.display_label')
            ->orderBy('s.scope_uuid')
            ->offset($offset)
            ->limit($limit)
            ->get($this->publicColumns());

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->publicScope($row);
        }

        return [
            'items' => $items,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + count($items)) < $total,
            'query' => $query,
        ];
    }

    private function activeScopes(): Builder
    {
        return $this->db->table('flatrate_wiki_scopes as s')
            ->join('flatrate_wiki_projection_state as ps', function ($join) {
                $join->on('ps.active_graph_version_uuid', '=', 's.graph_version_uuid')
                    ->on('ps.community_uuid', '=', 's.community_uuid');
            })
            ->where('s.lifecycle_status', 'active');
    }

    private function childrenQuery(string $scopeUuid): Builder
    {
        return $this->activeScopes()
            ->where('s.primary_parent_scope_uuid', strtolower($scopeUuid));
    }

    private function normalChildrenQuery(string $scopeUuid): Builder
    {
        return $this->childrenQuery($scopeUuid)
            ->where('s.is_catch_all', false);
    }

    private function publicColumns(): array
    {
        return [
            's.scope_uuid',
            's.scope_type',
            's.owning_board_key',
            's.route_key',
            's.display_label',
            's.primary_parent_scope_uuid',
            's.is_catch_all',
            's.discussion_capable',
        ];
    }

    private function publicScope(object $row): array
    {
        return [
            'id' => strtolower((string) $row->scope_uuid),
            'type' => (string) $row->scope_type,
            'label' => (string) $row->display_label,
            'route' => $row->route_key !== null ? (string) $row->route_key : null,
            'owningBoardKey' => $row->owning_board_key !== null ? (string) $row->owning_board_key : null,
            'parentId' => $row->primary_parent_scope_uuid !== null
                ? strtolower((string) $row->primary_parent_scope_uuid)
                : null,
            'discussionCapable' => (bool) $row->discussion_capable,
            'isCatchAll' => (bool) $row->is_catch_all,
        ];
    }
}
