<?php

namespace FlatRate\WikiContext\Preview;

use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use RuntimeException;

/**
 * Shared acceptance orchestration for API and CLI surfaces.
 */
final class PreviewAcceptanceService
{
    public function __construct(
        private PreviewAuthorization $auth,
        private SettingsRepositoryInterface $settings,
        private RuntimeBindingAuthority $bindings,
        private PreviewFixtureRunner $fixtures,
        private PreviewAcceptanceRepository $receipts
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function accept(User $actor): array
    {
        $this->auth->assertAdmin($actor);

        if (!$this->enabled(FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED)) {
            throw new RuntimeException('wiki_preview_closed');
        }

        $bindings = $this->bindings->current();
        $matrix = $this->fixtures->runRequiredAudiences();
        $readOnly = $this->fixtures->runReadOnlyValidations();

        foreach ($readOnly as $check) {
            if (($check['check'] ?? '') === 'dry_run_context_validation') {
                if (($check['status'] ?? null) !== 'PASS') {
                    throw new RuntimeException('preview_accept_readonly_validation_failed');
                }
                continue;
            }
            if (($check['allowed'] ?? true) !== false || ($check['status'] ?? null) !== 'DENIED') {
                throw new RuntimeException('preview_accept_mutation_not_denied:' . ($check['check'] ?? 'unknown'));
            }
        }

        if (!$matrix['pass']) {
            throw new RuntimeException('preview_accept_fixtures_failed');
        }

        $receipt = [
            'accepted_by_user_id' => (int) $actor->id,
            'accepted_at' => gmdate('Y-m-d H:i:s'),
            'active_graph_version_uuid' => $bindings['active_graph_version_uuid'],
            'extension_build_id' => $bindings['extension_build_id'],
            'wiki_contract_version' => $bindings['wiki_contract_version'],
            'directory_display_policy_digest' => $bindings['directory_display_policy_digest'],
            'audience_profiles_verified' => implode(',', $matrix['audiences']),
            'fixture_results_digest' => $matrix['fixture_results_digest'],
            'status' => 'PASS',
        ];

        $persisted = $this->receipts->insertPass($receipt);

        return [
            'ok' => true,
            'receipt' => $persisted,
            'fixture_matrix_pass' => true,
            'audiences' => $matrix['audiences'],
            'read_only_validations' => $readOnly,
            'same_user_component_path_claimed' => false,
            'production_mutation' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function statusSummary(): array
    {
        $bindings = null;
        $bindingsError = null;
        try {
            $bindings = $this->bindings->current();
        } catch (RuntimeException $e) {
            $bindingsError = $e->getMessage();
            $bindings = array_merge(
                [
                    'active_graph_version_uuid' => null,
                ],
                RuntimeBindingAuthority::packageBindings()
            );
        }

        $latest = $this->receipts->findLatest();
        $latestPass = $this->receipts->findLatestPass();
        $fresh = false;
        if ($latestPass !== null && $bindings !== null && $bindings['active_graph_version_uuid'] !== null) {
            $fresh = PreviewAcceptance::isFresh($latestPass, $bindings);
        }

        return [
            'creation_supported' => true,
            'latest' => $latest,
            'latest_pass' => $latestPass,
            'fresh' => $fresh,
            'current_bindings' => $bindings,
            'bindings_error' => $bindingsError,
        ];
    }

    private function enabled(string $key): bool
    {
        return filter_var(
            $this->settings->get($key, false),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
