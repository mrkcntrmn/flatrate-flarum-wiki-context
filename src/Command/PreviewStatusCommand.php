<?php

namespace FlatRate\WikiContext\Command;

use FlatRate\WikiContext\Support\FeatureGates;
use FlatRate\WikiContext\Support\GhostPreviewPolicy;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;

final class PreviewStatusCommand extends AbstractCommand
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
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
        $this->info('acceptance_receipt=P1B_NOT_IMPLEMENTED');
        $this->info('PRODUCTION_MUTATION=false');
    }
}
