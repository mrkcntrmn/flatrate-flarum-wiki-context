<?php

namespace FlatRate\WikiContext\Preview;

use FlatRate\WikiContext\Support\DirectoryDisplayPolicy;
use FlatRate\WikiContext\Support\Uuid;
use FlatRate\WikiContext\Support\WikiBuild;
use FlatRate\WikiContext\Support\WikiContract;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Resolves the exact binding tuple recorded on preview acceptance receipts.
 */
final class RuntimeBindingAuthority
{
    public function __construct(
        private ConnectionInterface $db,
        private ?string $communityUuid = null
    ) {
    }

    /**
     * @return array{
     *   active_graph_version_uuid:string,
     *   extension_build_id:string,
     *   wiki_contract_version:string,
     *   directory_display_policy_digest:string
     * }
     */
    public function current(): array
    {
        return [
            'active_graph_version_uuid' => $this->resolveActiveGraphVersionUuid(),
            'extension_build_id' => WikiBuild::BUILD_ID,
            'wiki_contract_version' => WikiContract::VERSION,
            'directory_display_policy_digest' => DirectoryDisplayPolicy::digest(),
        ];
    }

    /**
     * Static package/policy bindings without requiring an active projection row.
     * Used by freshness unit tests; acceptance persistence still requires current().
     *
     * @return array{
     *   extension_build_id:string,
     *   wiki_contract_version:string,
     *   directory_display_policy_digest:string
     * }
     */
    public static function packageBindings(): array
    {
        return [
            'extension_build_id' => WikiBuild::BUILD_ID,
            'wiki_contract_version' => WikiContract::VERSION,
            'directory_display_policy_digest' => DirectoryDisplayPolicy::digest(),
        ];
    }

    private function resolveActiveGraphVersionUuid(): string
    {
        $query = $this->db->table('flatrate_wiki_projection_state')
            ->orderBy('community_uuid');

        if ($this->communityUuid !== null && $this->communityUuid !== '') {
            $community = Uuid::normalize($this->communityUuid);
            if ($community === null) {
                throw new RuntimeException('preview_accept_invalid_community_uuid');
            }
            $query->where('community_uuid', $community);
        }

        $row = $query->first(['active_graph_version_uuid', 'community_uuid']);
        if ($row === null || $row->active_graph_version_uuid === null) {
            throw new RuntimeException('preview_accept_no_active_graph');
        }

        $graph = Uuid::normalize((string) $row->active_graph_version_uuid);
        if ($graph === null) {
            throw new RuntimeException('preview_accept_invalid_active_graph');
        }

        return $graph;
    }
}
