<?php

namespace FlatRate\WikiContext\Command;

use FlatRate\WikiContext\Preview\PreviewAcceptanceService;
use FlatRate\WikiContext\Preview\PreviewAuthorization;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\UserRepository;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;

/**
 * Operator CLI wrapper around PreviewAcceptanceService.
 *
 * CLI is not a logged-in browser session. An explicit admin user ID must be
 * supplied and verified before acceptance may run.
 */
final class PreviewAcceptCommand extends AbstractCommand
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private UserRepository $users,
        private PreviewAuthorization $auth,
        private PreviewAcceptanceService $acceptance
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('flatrate:wiki:preview-accept')
            ->setDescription('Record admin ghost-preview acceptance receipt via verified admin user ID.')
            ->addOption(
                'accepted-by',
                null,
                InputOption::VALUE_REQUIRED,
                'Flarum user ID of the authorizing administrator'
            );
    }

    protected function fire(): void
    {
        $raw = $this->input->getOption('accepted-by');
        if ($raw === null || $raw === '' || !ctype_digit((string) $raw)) {
            $this->error('preview_accept=MISSING_ACCEPTED_BY');
            throw new RuntimeException('preview_accept_requires_accepted_by_user_id');
        }

        $user = $this->users->findOrFail((int) $raw);

        try {
            $this->auth->assertAdmin($user);
        } catch (PermissionDeniedException $e) {
            $this->error('preview_accept=ACCEPTED_BY_NOT_ADMIN');
            throw $e;
        }

        if (!filter_var($this->settings->get(FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED, false), FILTER_VALIDATE_BOOLEAN)) {
            $this->error('preview_accept=WIKI_PREVIEW_CLOSED');
            throw new RuntimeException('wiki_preview_closed');
        }

        try {
            $result = $this->acceptance->accept($user);
        } catch (RuntimeException $e) {
            $this->error('preview_accept=' . $e->getMessage());
            throw $e;
        }

        $receipt = $result['receipt'];
        $this->info('preview_accept=PASS');
        $this->info('receipt_id=' . ($receipt['id'] ?? 'unknown'));
        $this->info('fixture_results_digest=' . $receipt['fixture_results_digest']);
        $this->info('active_graph_version_uuid=' . $receipt['active_graph_version_uuid']);
        $this->info('extension_build_id=' . $receipt['extension_build_id']);
        $this->info('wiki_contract_version=' . $receipt['wiki_contract_version']);
        $this->info('same_user_component_path_claimed=false');
        $this->info('PRODUCTION_MUTATION=false');
    }
}
