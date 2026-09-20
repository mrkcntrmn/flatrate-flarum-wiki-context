<?php

namespace FlatRate\WikiContext\Repository;

use Illuminate\Database\ConnectionInterface;

final class ProjectionStateRepository
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function find(string $communityUuid): ?object
    {
        return $this->db->table('flatrate_wiki_projection_state')
            ->where('community_uuid', strtolower($communityUuid))
            ->first();
    }
}
