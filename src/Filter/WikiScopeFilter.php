<?php

namespace FlatRate\WikiContext\Filter;

use Flarum\Filter\FilterInterface;
use Flarum\Filter\FilterState;
use Illuminate\Database\Query\Builder;

/**
 * Native Flarum discussion filter: filter[wiki-scope]=<scope-uuid>
 *
 * Membership (EXISTS preferred):
 *   primary exact OR primary descendant of target
 *   OR active relevance exact OR active relevance descendant of target
 *
 * Outer Flarum query remains authoritative for visibility/sort/pagination.
 * CLIENT_SIDE_FEED_MERGE=false
 */
class WikiScopeFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'wiki-scope';
    }

    public function filter(FilterState $filterState, string $filterValue, bool $negate): void
    {
        $scopeUuid = strtolower(trim($filterValue));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $scopeUuid)) {
            // Invalid scope → empty membership (fail closed for filter value).
            $filterState->getQuery()->whereRaw('0 = 1');

            return;
        }

        $filterState->getQuery()->where(function (Builder $query) use ($scopeUuid, $negate) {
            $method = $negate ? 'whereNotExists' : 'whereExists';

            $query->{$method}(function (Builder $sub) use ($scopeUuid) {
                $sub->selectRaw('1')
                    ->from('flatrate_wiki_discussion_context as wdc')
                    ->whereColumn('wdc.discussion_id', 'discussions.id')
                    ->where(function (Builder $inner) use ($scopeUuid) {
                        $inner->where('wdc.primary_scope_uuid', $scopeUuid)
                            ->orWhereExists(function (Builder $anc) use ($scopeUuid) {
                                $anc->selectRaw('1')
                                    ->from('flatrate_wiki_scope_ancestors as wsa')
                                    ->join('flatrate_wiki_projection_state as wps', function ($join) {
                                        $join->on('wps.active_graph_version_uuid', '=', 'wsa.graph_version_uuid');
                                    })
                                    ->whereColumn('wsa.descendant_scope_uuid', 'wdc.primary_scope_uuid')
                                    ->where('wsa.ancestor_scope_uuid', $scopeUuid);
                            });
                    });
            });

            $orMethod = $negate ? 'whereNotExists' : 'orWhereExists';
            $query->{$orMethod}(function (Builder $sub) use ($scopeUuid) {
                $sub->selectRaw('1')
                    ->from('flatrate_wiki_discussion_relevance as wdr')
                    ->whereColumn('wdr.discussion_id', 'discussions.id')
                    ->whereNull('wdr.removed_at')
                    ->where(function (Builder $inner) use ($scopeUuid) {
                        $inner->where('wdr.relevant_scope_uuid', $scopeUuid)
                            ->orWhereExists(function (Builder $anc) use ($scopeUuid) {
                                $anc->selectRaw('1')
                                    ->from('flatrate_wiki_scope_ancestors as wsa')
                                    ->join('flatrate_wiki_projection_state as wps', function ($join) {
                                        $join->on('wps.active_graph_version_uuid', '=', 'wsa.graph_version_uuid');
                                    })
                                    ->whereColumn('wsa.descendant_scope_uuid', 'wdr.relevant_scope_uuid')
                                    ->where('wsa.ancestor_scope_uuid', $scopeUuid);
                            });
                    });
            });
        });
    }
}
