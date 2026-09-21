<?php

namespace FlatRate\WikiContext\Command;

use FlatRate\WikiContext\Projection\ProjectionService;
use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * CLI-only rollback. No remote/public rollback API.
 */
final class ProjectionRollbackCommand extends AbstractCommand
{
    public function __construct(private ProjectionService $projection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('flatrate:wiki:projection-rollback')
            ->setDescription('Operator CLI rollback to a historical accepted graph version.')
            ->addArgument('version', InputArgument::REQUIRED, 'Target graph_version_uuid')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Operator reason', 'unspecified')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report without switching')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Perform the pointer switch (required for non-dry-run)');
    }

    protected function fire(): void
    {
        $version = (string) $this->input->getArgument('version');
        $reason = (string) $this->input->getOption('reason');
        $dryRun = (bool) $this->input->getOption('dry-run');

        // Default to dry-run unless operator explicitly omits --dry-run AND passes --execute.
        // Keep fail-closed: without --execute, always dry-run even if --dry-run omitted.
        $execute = (bool) $this->input->getOption('execute');
        $effectiveDryRun = $dryRun || !$execute;

        $result = $this->projection->rollback($version, $reason, $effectiveDryRun);

        $this->info('projection_rollback=IMPLEMENTED');
        $this->info('target_version=' . $version);
        $this->info('reason=' . $reason);
        $this->info('dry_run=' . ($effectiveDryRun ? 'true' : 'false'));
        $this->info('remote_rollback_api=false');
        $this->info('status=' . ($result['status'] ?? 'unknown'));

        if (($result['status'] ?? '') === 'reject') {
            $this->error('reason=' . ($result['reason'] ?? 'unknown'));
            throw new \RuntimeException('projection_rollback_rejected');
        }

        if (!$effectiveDryRun) {
            $this->info('from=' . ($result['from'] ?? ''));
            $this->info('to=' . ($result['to'] ?? ''));
        }
    }
}
