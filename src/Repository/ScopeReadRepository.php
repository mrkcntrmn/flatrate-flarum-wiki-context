<?php

namespace FlatRate\WikiContext\Repository;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final class ScopeReadRepository
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    /**
     * Return one active scope from the currently active projection.
     *
     * @return array<string,mixed>|null
     */
    public function findActive(string $scopeUuid): ?array
    {
        $row = $this->activeScopes()
            ->where('s.scope_uuid', strtolower($scopeUuid))
            ->first($this->publicColumns());

        return $row ? $this->publicScope($row) : null;
    }

    /**
     * Root-to-current breadcrumb using only the active projection closure.
     *
     * @return list<array<string,mixed>>
     */
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

    /**
     * @return array{items:list<array<string,mixed>>,total:int,offset:int,limit:int,has_more:bool}
     */
    public function children(string $scopeUuid, int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(50, $limit));

        $base = $this->childrenQuery($scopeUuid);
        $total = (int) (clone $base)->count();

        $rows = $base
            ->orderBy('s.is_catch_all')
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

    /**
     * @return list<string>
     */
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

    /**
     * @return array<string,mixed>
     */
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
