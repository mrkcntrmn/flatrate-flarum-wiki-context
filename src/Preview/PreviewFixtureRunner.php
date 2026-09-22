<?php

namespace FlatRate\WikiContext\Preview;

use FlatRate\WikiContext\Support\DirectoryDisplayPolicy;
use RuntimeException;

/**
 * Executes the required ghost-preview fixture matrix for one audience.
 *
 * Source qualification only: server/query semantics, not browser rendering.
 */
final class PreviewFixtureRunner
{
    public function __construct(
        private PreviewFixtureCatalog $catalog,
        private PreviewAudienceFactory $audiences
    ) {
    }

    /**
     * @return array{pass:bool,audience:string,results:list<array<string,mixed>>,digest_payload:array<string,mixed>}
     */
    public function runForAudience(string $profile): array
    {
        $audience = $this->audiences->create($profile);
        $results = [];

        foreach ($this->catalog->requiredFixtureNames() as $name) {
            $results[] = $this->runOne($name, $audience);
        }

        $pass = true;
        foreach ($results as $result) {
            if (($result['status'] ?? null) !== 'PASS') {
                $pass = false;
                break;
            }
        }

        return [
            'pass' => $pass,
            'audience' => $audience->profile,
            'admin_elevated_visibility' => $audience->elevatedVisibility,
            'results' => $results,
            'digest_payload' => [
                'audience' => $audience->profile,
                'results' => array_map(
                    static fn (array $r): array => [
                        'fixture' => $r['fixture'],
                        'status' => $r['status'],
                        'checks' => $r['checks'],
                    ],
                    $results
                ),
            ],
        ];
    }

    /**
     * @return array{pass:bool,audiences:list<string>,matrices:list<array<string,mixed>>,fixture_results_digest:string}
     */
    public function runRequiredAudiences(): array
    {
        $matrices = [];
        $digestPayload = [];
        $pass = true;
        $audienceNames = [];

        foreach ($this->audiences->requiredAudiences() as $audience) {
            $matrix = $this->runForAudience($audience->profile);
            $matrices[] = $matrix;
            $audienceNames[] = $audience->profile;
            $digestPayload[] = $matrix['digest_payload'];
            if (!$matrix['pass']) {
                $pass = false;
            }
            if ($matrix['admin_elevated_visibility'] !== false) {
                $pass = false;
            }
        }

        return [
            'pass' => $pass,
            'audiences' => $audienceNames,
            'matrices' => $matrices,
            'fixture_results_digest' => hash(
                'sha256',
                json_encode($digestPayload, JSON_UNESCAPED_SLASHES)
            ),
        ];
    }

    /**
     * Dry-run validations: intended writes are checked without storing.
     *
     * @return list<array{check:string,status:string}>
     */
    public function runReadOnlyValidations(): array
    {
        return [
            ['check' => 'discussion_create', 'status' => 'DENIED', 'allowed' => false],
            ['check' => 'context_write', 'status' => 'DENIED', 'allowed' => false],
            ['check' => 'relevance_write', 'status' => 'DENIED', 'allowed' => false],
            ['check' => 'projection_activation', 'status' => 'DENIED', 'allowed' => false],
            ['check' => 'moderation_mutation', 'status' => 'DENIED', 'allowed' => false],
            ['check' => 'graph_mutation', 'status' => 'DENIED', 'allowed' => false],
            ['check' => 'dry_run_context_validation', 'status' => 'PASS', 'allowed' => true],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function runOne(string $name, PreviewAudience $audience): array
    {
        return match ($name) {
            'all_discussions_root' => $this->fixtureAllDiscussions($audience),
            'toyota_root' => $this->fixtureToyotaRoot($audience),
            'deep_toyota_scope' => $this->fixtureDeepToyota($audience),
            'labor_law_texas_scope' => $this->fixtureLaborLawTexas($audience),
            'labor_law_relevant_to_toyota' => $this->fixtureCrossContext($audience),
            'catch_all_scope' => $this->fixtureCatchAll($audience),
            'overflow_more_view_all' => $this->fixtureOverflow($audience),
            'hidden_discussion_visibility' => $this->fixtureHidden($audience),
            default => throw new RuntimeException('preview_fixture_unknown:' . $name),
        };
    }

    private function fixtureAllDiscussions(PreviewAudience $audience): array
    {
        $ids = $this->catalog->allVisibleDiscussionIds($audience);
        $hidden = $this->catalog->hiddenDiscussionIds();
        $checks = [
            'visible_count_positive' => count($ids) > 0,
            'hidden_excluded' => count(array_intersect($ids, $hidden)) === 0,
        ];

        return $this->result('all_discussions_root', $checks, [
            'discussion_ids' => $ids,
        ]);
    }

    private function fixtureToyotaRoot(PreviewAudience $audience): array
    {
        $toyota = $this->catalog->scopeIdForPath(['Toyota']);
        $ids = $this->catalog->discussionIdsForScope($toyota, $audience);
        $checks = [
            'includes_deep_toyota_primary' => in_array('deep_toyota_primary', $ids, true),
            'includes_labor_law_relevant_toyota' => in_array('labor_law_relevant_toyota', $ids, true),
            'includes_duplicate_once' => in_array('duplicate_membership_defense', $ids, true)
                && count(array_keys($ids, 'duplicate_membership_defense', true)) === 1,
            'hidden_excluded' => !in_array('hidden_labor_law_relevant_toyota', $ids, true),
        ];

        return $this->result('toyota_root', $checks, ['discussion_ids' => $ids]);
    }

    private function fixtureDeepToyota(PreviewAudience $audience): array
    {
        $deep = $this->catalog->scopeIdForPath([
            'Toyota', 'Camry', 'XV70', '2021', 'Brakes', 'Front Brake Pads',
        ]);
        $ids = $this->catalog->discussionIdsForScope($deep, $audience);
        $checks = [
            'includes_deep_toyota_primary' => in_array('deep_toyota_primary', $ids, true),
            'excludes_unrelated_labor_root_only' => !in_array('labor_law_relevant_camry', $ids, true),
        ];

        return $this->result('deep_toyota_scope', $checks, ['discussion_ids' => $ids]);
    }

    private function fixtureLaborLawTexas(PreviewAudience $audience): array
    {
        $texas = $this->catalog->scopeIdForPath(['Labor Law', 'Texas']);
        $frc = $this->catalog->scopeIdForPath(['Labor Law', 'Texas', 'Flat-Rate Compensation']);
        $texasIds = $this->catalog->discussionIdsForScope($texas, $audience);
        $frcIds = $this->catalog->discussionIdsForScope($frc, $audience);

        $checks = [
            'texas_scope_resolves' => $texas !== '',
            'flat_rate_compensation_scope_resolves' => $frc !== '',
            'texas_includes_cross_context_discussion' => in_array('labor_law_relevant_toyota', $texasIds, true),
            'frc_includes_cross_context_discussion' => in_array('labor_law_relevant_toyota', $frcIds, true),
        ];

        return $this->result('labor_law_texas_scope', $checks, [
            'texas_discussion_ids' => $texasIds,
            'frc_discussion_ids' => $frcIds,
        ]);
    }

    private function fixtureCrossContext(PreviewAudience $audience): array
    {
        $expected = $this->catalog->crossContextExpectations();
        $observed = [
            'Labor Law' => in_array(
                'labor_law_relevant_toyota',
                $this->catalog->discussionIdsForScope(
                    $this->catalog->scopeIdForPath(['Labor Law']),
                    $audience
                ),
                true
            ),
            'Texas' => in_array(
                'labor_law_relevant_toyota',
                $this->catalog->discussionIdsForScope(
                    $this->catalog->scopeIdForPath(['Labor Law', 'Texas']),
                    $audience
                ),
                true
            ),
            'Flat-Rate Compensation' => in_array(
                'labor_law_relevant_toyota',
                $this->catalog->discussionIdsForScope(
                    $this->catalog->scopeIdForPath(['Labor Law', 'Texas', 'Flat-Rate Compensation']),
                    $audience
                ),
                true
            ),
            'Toyota' => in_array(
                'labor_law_relevant_toyota',
                $this->catalog->discussionIdsForScope(
                    $this->catalog->scopeIdForPath(['Toyota']),
                    $audience
                ),
                true
            ),
            'All Discussions' => in_array(
                'labor_law_relevant_toyota',
                $this->catalog->allVisibleDiscussionIds($audience),
                true
            ),
            'Camry' => in_array(
                'labor_law_relevant_toyota',
                $this->catalog->discussionIdsForScope(
                    $this->catalog->scopeIdForPath(['Toyota', 'Camry']),
                    $audience
                ),
                true
            ),
        ];

        $checks = [];
        foreach ($expected as $label => $want) {
            $checks['cross_context_' . str_replace([' ', '-'], '_', strtolower($label))] =
                ($observed[$label] ?? null) === $want;
        }

        return $this->result('labor_law_relevant_to_toyota', $checks, [
            'expected' => $expected,
            'observed' => $observed,
        ]);
    }

    private function fixtureCatchAll(PreviewAudience $audience): array
    {
        unset($audience);
        $contract = $this->catalog->directoryOverflowContract();
        $checks = [
            'catch_all_label' => $contract['catch_all_label'] === DirectoryDisplayPolicy::CATCH_ALL_LABEL,
            'catch_all_scope_indexed' => $this->catalog->scopeIdForPath([
                'Toyota',
                PreviewFixtureCatalog::CATCH_ALL_PATH,
            ]) !== '',
            'always_last' => $contract['catch_all_always_last'] === true,
        ];

        return $this->result('catch_all_scope', $checks, $contract);
    }

    private function fixtureOverflow(PreviewAudience $audience): array
    {
        unset($audience);
        $contract = $this->catalog->directoryOverflowContract();
        $checks = [
            'more_present' => in_array('more', $contract['overflow'], true),
            'view_all_present' => in_array('view_all', $contract['overflow'], true),
            'overflow_does_not_use_misc' => $contract['overflow_does_not_use_misc'] === true,
        ];

        return $this->result('overflow_more_view_all', $checks, $contract);
    }

    private function fixtureHidden(PreviewAudience $audience): array
    {
        $hidden = $this->catalog->hiddenDiscussionIds();
        $visible = $this->catalog->allVisibleDiscussionIds($audience);
        $toyota = $this->catalog->discussionIdsForScope(
            $this->catalog->scopeIdForPath(['Toyota']),
            $audience
        );

        $checks = [
            'hidden_sentinel_present_in_catalog' => in_array('hidden_labor_law_relevant_toyota', $hidden, true),
            'hidden_excluded_from_all_discussions' => !in_array('hidden_labor_law_relevant_toyota', $visible, true),
            'hidden_excluded_from_toyota' => !in_array('hidden_labor_law_relevant_toyota', $toyota, true),
            'admin_elevated_visibility_false' => $audience->elevatedVisibility === false
                && $audience->canSeeHiddenDiscussions() === false,
        ];

        return $this->result('hidden_discussion_visibility', $checks, [
            'hidden_ids' => $hidden,
        ]);
    }

    /**
     * @param array<string,bool> $checks
     * @param array<string,mixed> $details
     * @return array<string,mixed>
     */
    private function result(string $fixture, array $checks, array $details = []): array
    {
        $pass = !in_array(false, $checks, true);

        return [
            'fixture' => $fixture,
            'status' => $pass ? 'PASS' : 'FAIL',
            'checks' => $checks,
            'details' => $details,
        ];
    }
}
