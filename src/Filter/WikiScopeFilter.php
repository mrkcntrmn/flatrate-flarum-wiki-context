<?php

namespace FlatRate\WikiContext\Filter;

use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Filter\FilterInterface;
use Flarum\Filter\FilterState;
use Illuminate\Database\Query\Builder;

/**
 * Native Flarum discussion filter: filter[wiki-scope]=<scope-uuid>
 *
 * Membership:
 *   primary exact OR primary descendant of target in the active graph
 *   OR active relevance exact OR active relevance descendant in the active graph
 *
 * Stable discussion assignments are reinterpreted through the active projection.
 * Outer Flarum query remains authoritative for visibility/sort/pagination.
 * CLIENT_SIDE_FEED_MERGE=false
 */
class WikiScopeFilter implements FilterInterface
{
    public function __construct(private SettingsReader $settings)
    {
    }

    public function getFilterKey(): string
    {
        return 'wiki-scope';
    }

    public function filter(FilterState $filterState, string $filterValue, bool $negate): void
    {
        $query = $filterState->getQuery();

        // The ordinary/member-facing filter stays completely dark until both
        // the derived-feed capability and public rollout are explicitly open.
        // WIKI-001P ghost preview must use a separately authorized server path.
        if (
            !$this->settings->bool(FeatureGates::DERIVED_FEEDS_ENABLED)
            || !$this->settings->bool(FeatureGates::PUBLIC_ROLLOUT_ENABLED)
        ) {
            $query->whereRaw('0 = 1');

            return;
        }

        $scopeUuid = strtolower(trim($filterValue));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $scopeUuid)) {
            // Invalid scope -> empty membership, even for a negated filter.
            $query->whereRaw('0 = 1');

            return;
        }

        // A UUID is queryable only when that target scope is active in the
        // currently active projection. This also prevents retired/unknown
        // targets from turning a negated filter into "all discussions".
        $query->whereExists(function (Builder $target) use ($scopeUuid) {
            $target->selectRaw('1')
                ->from('flatrate_wiki_scopes as wts')
                ->join('flatrate_wiki_projection_state as wtps', function ($join) {
                    $join->on('wtps.active_graph_version_uuid', '=', 'wts.graph_version_uuid')
                        ->on('wtps.community_uuid', '=', 'wts.community_uuid');
                })
                ->where('wts.scope_uuid', $scopeUuid)
                ->where('wts.lifecycle_status', 'active');
        });

        $query->where(function (Builder $membership) use ($scopeUuid, $negate) {
            $primaryMethod = $negate ? 'whereNotExists' : 'whereExists';

            $membership->{$primaryMethod}(function (Builder $sub) use ($scopeUuid) {
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
                                    ->join('flatrate_wiki_scopes as was', function ($join) {
                                        $join->on('was.graph_version_uuid', '=', 'wsa.graph_version_uuid')
                                            ->on('was.scope_uuid', '=', 'wsa.descendant_scope_uuid')
                                            ->on('was.community_uuid', '=', 'wps.community_uuid');
                                    })
                                    ->whereColumn('wsa.descendant_scope_uuid', 'wdc.primary_scope_uuid')
                                    ->where('wsa.ancestor_scope_uuid', $scopeUuid)
                                    ->where('was.lifecycle_status', 'active');
                            });
                    });
            });

            $relevanceMethod = $negate ? 'whereNotExists' : 'orWhereExists';

            $membership->{$relevanceMethod}(function (Builder $sub) use ($scopeUuid) {
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
                                    ->join('flatrate_wiki_scopes as was', function ($join) {
                                        $join->on('was.graph_version_uuid', '=', 'wsa.graph_version_uuid')
                                            ->on('was.scope_uuid', '=', 'wsa.descendant_scope_uuid')
                                            ->on('was.community_uuid', '=', 'wps.community_uuid');
                                    })
                                    ->whereColumn('wsa.descendant_scope_uuid', 'wdr.relevant_scope_uuid')
                                    ->where('wsa.ancestor_scope_uuid', $scopeUuid)
                                    ->where('was.lifecycle_status', 'active');
                            });
                    });
            });
        });
    }
}
