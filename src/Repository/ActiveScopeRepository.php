<?php

namespace FlatRate\WikiContext\Repository;

use Illuminate\Database\ConnectionInterface;

final class ActiveScopeRepository
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function find(string $scopeUuid): ?object
    {
        $rows = $this->db->table('flatrate_wiki_scopes as ws')
            ->join('flatrate_wiki_projection_state as wps', function ($join) {
                $join->on('wps.community_uuid', '=', 'ws.community_uuid')
                    ->on('wps.active_graph_version_uuid', '=', 'ws.graph_version_uuid');
            })
            ->where('ws.scope_uuid', strtolower($scopeUuid))
            ->where('ws.lifecycle_status', 'active')
            ->select([
                'ws.scope_uuid',
                'ws.graph_version_uuid',
                'ws.community_uuid',
                'ws.scope_type',
                'ws.owning_board_key',
                'ws.route_key',
                'ws.display_label',
                'ws.discussion_capable',
                'ws.is_catch_all',
                'wps.active_generation',
            ])
            ->get();

        // A Flarum Community must resolve one public active semantic scope UUID.
        return count($rows) === 1 ? $rows[0] : null;
    }

    /**
     * @param list<string> $scopeUuids
     * @return array<string,object>
     */
    public function findMany(array $scopeUuids): array
    {
        $result = [];

        foreach (array_values(array_unique(array_map('strtolower', $scopeUuids))) as $scopeUuid) {
            $row = $this->find($scopeUuid);
            if ($row) {
                $result[$scopeUuid] = $row;
            }
        }

        return $result;
    }
}
