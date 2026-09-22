<?php

namespace FlatRate\WikiContext\Command;

use FlatRate\WikiContext\Preview\PreviewAcceptanceService;
use FlatRate\WikiContext\Support\FeatureGates;
use FlatRate\WikiContext\Support\GhostPreviewPolicy;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;

final class PreviewStatusCommand extends AbstractCommand
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private PreviewAcceptanceService $acceptance
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('flatrate:wiki:preview-status')
            ->setDescription('Report admin ghost-preview gate and acceptance readiness.');
    }

    protected function fire(): void
    {
        $this->info('admin_ghost_preview_enabled=' . ($this->settings->get(FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED) ? 'true' : 'false'));
        $this->info('public_rollout_enabled=' . ($this->settings->get(FeatureGates::PUBLIC_ROLLOUT_ENABLED) ? 'true' : 'false'));
        $this->info('default_audience=' . GhostPreviewPolicy::DEFAULT_AUDIENCE);
        $this->info('admin_elevated_visibility_for_user_preview=false');
        $this->info('server_preview_authorization=ADMIN_ONLY');
        $this->info('preview_status_api=/api/flatrate-wiki/preview/status');
        $this->info('preview_accept_api=/api/flatrate-wiki/preview/accept');

        $summary = $this->acceptance->statusSummary();
        $this->info('acceptance_receipt_creation_supported=' . ($summary['creation_supported'] ? 'true' : 'false'));
        $this->info('acceptance_receipt_fresh=' . ($summary['fresh'] ? 'true' : 'false'));
        if ($summary['latest_pass'] !== null) {
            $this->info('acceptance_receipt_latest_pass_id=' . $summary['latest_pass']['id']);
            $this->info('acceptance_receipt_fixture_digest=' . $summary['latest_pass']['fixture_results_digest']);
        } else {
            $this->info('acceptance_receipt_latest_pass=none');
        }
        if ($summary['bindings_error'] !== null) {
            $this->info('acceptance_bindings_error=' . $summary['bindings_error']);
        }
        $this->info('same_user_component_path_claimed=false');
        $this->info('PRODUCTION_MUTATION=false');
    }
}
